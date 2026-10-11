<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\HotelService;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Services\ReservationStatusService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private ReservationService $reservationService,
        private ReservationStatusService $statusService,
    ) {}

    public function index(Request $request)
    {
        return Payment::with([
            'processor:id,name',
            'reservation.user',
            'reservation.guest',
            'reservation.room',
            'reservation.services.service',
            'reservation.invoice',
        ])->when($request->method, fn ($query, $method) => $query->where('payment_method', $method))
            ->when(
                $request->user()?->hasRole('User'),
                fn ($query) => $query->whereHas('reservation', fn ($reservation) => $reservation->where('user_id', $request->user()->id))
            )
            ->latest('paid_at')
            ->paginate($request->integer('per_page', 15));
    }

    public function store(Request $request, Reservation $reservation)
    {
        $this->ensureReservationAccess($request, $reservation);

        $data = $request->validate([
            'payment_method' => 'required|in:Cash,Debit Card,Credit Card,Bank Transfer,QRIS',
            'paid_amount' => 'required|numeric|gt:0',
            'notes' => 'nullable|string|max:500',
            'services' => 'nullable|array|min:1',
            'services.*.hotel_service_id' => 'required|exists:hotel_services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'hotel_service_id' => 'nullable|exists:hotel_services,id',
            'quantity' => 'nullable|integer|min:1|required_with:hotel_service_id',
        ]);

        return DB::transaction(function () use ($request, $reservation, $data) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_if(in_array($reservation->status, [ReservationStatus::Cancelled->value, ReservationStatus::CheckedOut->value, ReservationStatus::Completed->value, ReservationStatus::NoShow->value], true), 422, 'Reservasi ini tidak dapat menerima pembayaran baru.');

            $serviceItems = $data['services'] ?? [];
            if (! empty($data['hotel_service_id'])) {
                $serviceItems[] = [
                    'hotel_service_id' => $data['hotel_service_id'],
                    'quantity' => $data['quantity'] ?? 1,
                ];
            }

            foreach ($serviceItems as $item) {
                $service = HotelService::findOrFail($item['hotel_service_id']);
                $quantity = (int) $item['quantity'];

                $reservation->services()->create([
                    'hotel_service_id' => $service->id,
                    'quantity' => $quantity,
                    'price' => $service->price,
                    'subtotal' => Money::multiply($service->price, $quantity),
                ]);
            }

            if ($serviceItems) {
                $this->reservationService->recalculate($reservation);
                $reservation->refresh();
            }

            $balance = $this->reservationService->balance($reservation);
            abort_if(Money::compare($balance, '0.00') <= 0, 422, 'Reservasi ini sudah lunas.');

            $tendered = Money::normalize($data['paid_amount']);
            abort_if($data['payment_method'] !== 'Cash' && Money::compare($tendered, $balance) > 0, 422, 'Pembayaran non-tunai tidak boleh melebihi sisa tagihan.');

            $appliedAmount = Money::min($tendered, $balance);
            $changeAmount = $data['payment_method'] === 'Cash' ? Money::max(Money::subtract($tendered, $appliedAmount), '0.00') : '0.00';

            $payment = $reservation->payments()->create([
                'payment_number' => $this->transactionNumber('PAY', $reservation),
                'payment_method' => $data['payment_method'],
                'amount' => $appliedAmount,
                'paid_amount' => $tendered,
                'change_amount' => $changeAmount,
                'payment_status' => 'Paid',
                'transaction_type' => 'Payment',
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
                'processed_by' => $request->user()->id,
            ]);

            if ($reservation->status === ReservationStatus::Pending->value) {
                $this->statusService->transition($reservation, ReservationStatus::Confirmed, $request->user()->id, 'Otomatis dikonfirmasi saat pembayaran dibuat.', 'reservation.confirmed');
                $this->reservationService->syncRoomStatus($reservation->room()->lockForUpdate()->firstOrFail());
            }

            ActivityLog::recordStatusChange($request->user()->id, 'payment.created', 'payment', $payment->id, null, 'Paid', $payment->payment_number.' - '.$payment->amount);

            return response()->json($payment->load([
                'processor:id,name',
                'reservation.user',
                'reservation.guest',
                'reservation.room',
                'reservation.services.service',
            ]), 201);
        });
    }

    public function refund(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'amount' => 'nullable|numeric|gt:0',
            'payment_method' => 'nullable|in:Cash,Debit Card,Credit Card,Bank Transfer,QRIS',
            'reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $request, $payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $refundable = $this->reservationService->refundableAmount($payment);
            abort_if(Money::compare($refundable, '0.00') <= 0, 422, 'Transaksi ini tidak memiliki saldo yang dapat direfund.');

            $amount = Money::normalize($data['amount'] ?? $refundable);
            abort_if(Money::compare($amount, $refundable) > 0, 422, 'Nominal refund melebihi saldo transaksi yang dapat direfund.');

            $refund = $payment->reservation->payments()->create([
                'payment_number' => $this->transactionNumber('REF', $payment->reservation),
                'payment_method' => $data['payment_method'] ?? $payment->payment_method,
                'amount' => $amount,
                'paid_amount' => $amount,
                'change_amount' => 0,
                'payment_status' => 'Refunded',
                'transaction_type' => 'Refund',
                'reference_payment_id' => $payment->id,
                'notes' => $data['reason'],
                'paid_at' => now(),
                'processed_by' => $request->user()->id,
            ]);
            $this->updateInvoiceSettlement($payment->reservation);

            ActivityLog::recordStatusChange($request->user()->id, 'payment.refunded', 'payment', $refund->id, null, 'Refunded', $data['reason']);

            return response()->json($refund->load('reservation'), 201);
        });
    }

    public function void(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $request, $payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->transaction_type === 'Payment' && $payment->payment_status === 'Paid', 422, 'Hanya pembayaran aktif yang dapat dibatalkan.');
            abort_if($payment->refunds()->where('payment_status', 'Refunded')->exists(), 422, 'Pembayaran yang sudah memiliki refund tidak dapat di-void.');

            $oldStatus = $payment->payment_status;
            $payment->update([
                'payment_status' => 'Voided',
                'notes' => trim(collect([$payment->notes, 'VOID: '.$data['reason']])->filter()->implode(PHP_EOL)),
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
            ]);
            $this->updateInvoiceSettlement($payment->reservation);

            ActivityLog::recordStatusChange($request->user()->id, 'payment.voided', 'payment', $payment->id, $oldStatus, 'Voided', $data['reason']);

            return $payment->fresh()->load('reservation');
        });
    }

    public function folio(Request $request, Reservation $reservation): array
    {
        $this->ensureReservationAccess($request, $reservation);
        $reservation->load(['user', 'guest', 'room.roomType', 'services.service', 'payments.processor', 'invoice']);

        $lineItems = [[
            'type' => 'Room',
            'description' => ($reservation->room?->roomType?->name ?? 'Room').' × '.$reservation->number_of_nights.' malam',
            'quantity' => $reservation->number_of_nights,
            'unit_price' => Money::normalize($reservation->room_price),
            'total' => Money::multiply($reservation->room_price, (int) $reservation->number_of_nights),
        ]];

        foreach ($reservation->services as $service) {
            $lineItems[] = [
                'type' => 'Service',
                'description' => $service->service?->name ?? 'Service',
                'quantity' => $service->quantity,
                'unit_price' => Money::normalize($service->price),
                'total' => Money::normalize($service->subtotal),
            ];
        }

        return [
            'reservation' => $reservation,
            'line_items' => $lineItems,
            'summary' => [
                'subtotal' => Money::jsonValue($reservation->subtotal),
                'discount' => Money::jsonValue($reservation->discount),
                'service_charge' => Money::jsonValue($reservation->service_charge),
                'tax' => Money::jsonValue($reservation->tax),
                'grand_total' => Money::jsonValue($reservation->grand_total),
                'net_paid' => Money::jsonValue($this->reservationService->netPaid($reservation)),
                'balance' => Money::jsonValue($this->reservationService->balance($reservation)),
            ],
        ];
    }

    private function ensureReservationAccess(Request $request, Reservation $reservation): void
    {
        abort_if(
            $request->user()?->hasRole('User') && $reservation->user_id !== $request->user()->id,
            403,
            'Anda hanya dapat mengakses pembayaran reservasi milik sendiri.'
        );
    }

    private function transactionNumber(string $prefix, Reservation $reservation): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.$reservation->id.'-'.Str::upper(Str::random(4));
    }

    private function updateInvoiceSettlement(Reservation $reservation): void
    {
        if (! $reservation->invoice()->exists()) {
            return;
        }

        $reservation->invoice()->update([
            'paid_total' => $this->reservationService->netPaid($reservation),
            'balance_due' => $this->reservationService->balance($reservation),
        ]);
    }
}
