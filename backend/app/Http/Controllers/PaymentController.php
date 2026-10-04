<?php

namespace App\Http\Controllers;

use App\Models\HotelService;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        private ReservationService $reservationService
    ) {}

    public function index(Request $request)
    {
        return Payment::with([
            'reservation.user',
            'reservation.room',
            'reservation.services.service',
            'reservation.invoice',
        ])->when($request->method, fn ($q, $v) => $q->where('payment_method', $v))
            ->when(
                $request->user()?->hasRole('User'),
                fn ($q) => $q->whereHas('reservation', fn ($reservation) => $reservation->where('user_id', $request->user()->id))
            )
            ->latest('paid_at')
            ->paginate(15);
    }

    public function store(Request $request, Reservation $reservation)
    {
        abort_unless(
            $request->user()?->hasRole('User'),
            403,
            'Admin, Receptionist, dan Manager hanya dapat melihat pembayaran.'
        );

        abort_if(
            $request->user()?->hasRole('User') && $reservation->user_id !== $request->user()->id,
            403,
            'Anda hanya dapat membayar reservasi milik sendiri.'
        );

        $data = $request->validate([
            'payment_method' => 'required|in:Cash,Debit Card,Credit Card,Bank Transfer,QRIS',
            'paid_amount' => 'required|numeric|min:0',
            'services' => 'nullable|array|min:1',
            'services.*.hotel_service_id' => 'required|exists:hotel_services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'hotel_service_id' => 'nullable|exists:hotel_services,id',
            'quantity' => 'nullable|integer|min:1|required_with:hotel_service_id',
        ]);

        return DB::transaction(function () use ($request, $reservation, $data) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_if(in_array($reservation->status, ['Cancelled', 'Checked Out', 'Completed']), 422, 'Reservasi ini tidak dapat menerima pembayaran.');

            $serviceItems = $data['services'] ?? [];
            if (!empty($data['hotel_service_id'])) {
                $serviceItems[] = [
                    'hotel_service_id' => $data['hotel_service_id'],
                    'quantity' => $data['quantity'] ?? 1,
                ];
            }

            if ($serviceItems) {
                foreach ($serviceItems as $item) {
                    $service = HotelService::where('status', true)->findOrFail($item['hotel_service_id']);
                    $quantity = (int) $item['quantity'];

                    $reservation->services()->create([
                        'hotel_service_id' => $service->id,
                        'quantity' => $quantity,
                        'price' => $service->price,
                        'subtotal' => (float) $service->price * $quantity,
                    ]);
                }
                $this->reservationService->recalculate($reservation);
                $reservation->refresh();
            }

            $amount = (float) $reservation->grand_total;
            $paidAmount = (float) $reservation->payments()->where('payment_status', 'Paid')->sum('amount');
            $balance = round($amount - $paidAmount, 2);
            abort_if($balance <= 0, 422, 'Reservasi ini sudah lunas.');

            $paid = (float) $data['paid_amount'];
            abort_if($paid < $balance, 422, 'Nominal pembayaran kurang dari sisa tagihan.');

            $payment = $reservation->payments()->create([
                'payment_number' => 'PAY-'.now()->format('YmdHis').'-'.$reservation->id,
                'payment_method' => $data['payment_method'],
                'amount' => $balance,
                'paid_amount' => $paid,
                'change_amount' => $paid - $balance,
                'payment_status' => 'Paid',
                'paid_at' => now(),
                'processed_by' => $request->user()->id,
            ]);

            return response()->json($payment->load([
                'reservation.user',
                'reservation.room',
                'reservation.services.service',
            ]), 201);
        });
    }
}
