<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\HotelService;
use App\Models\HotelSetting;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\ReservationService;
use App\Services\ReservationStatusService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $service,
        private ReservationStatusService $statusService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',

            'status' => ['nullable', Rule::enum(ReservationStatus::class)],

            'date' => 'nullable|date_format:Y-m-d',

            'from' => 'nullable|date',

            'to' => 'nullable|date',

            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage =
            $request->integer(
                'per_page',
                15
            );

        return Reservation::with([
            'user',
            'guest',
            'membership',
            'room.roomType',
            'services.service',
            'payments',
        ])
            ->when(
                $request->user()?->hasRole('User'),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )

            /*
            |--------------------------------------------------------------------------
            | SEARCH
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled(
                    'search'
                ),

                function (
                    $query
                ) use (
                    $request
                ) {

                    $search =
                        $request->input(
                            'search'
                        );

                    $query->where(
                        function (
                            $query
                        ) use (
                            $search
                        ) {

                            $query
                                ->where(
                                    'booking_number',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhereHas(
                                    'user',

                                    function (
                                        $user
                                    ) use (
                                        $search
                                    ) {

                                        $user->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        );
                                    }
                                )

                                ->orWhereHas(
                                    'room',

                                    function (
                                        $room
                                    ) use (
                                        $search
                                    ) {

                                        $room->where(
                                            'room_number',
                                            'like',
                                            "%{$search}%"
                                        );
                                    }
                                );

                            $query->orWhereHas('guest', function ($guest) use ($search) {
                                $guest->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                        }
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled(
                    'status'
                ),

                fn ($query) => $query->where(
                    'status',
                    $request->input(
                        'status'
                    )
                )
            )

            /*
            |--------------------------------------------------------------------------
            | OPERATIONAL DATE FILTER
            |--------------------------------------------------------------------------
            |
            | Contoh:
            |
            | Check-in  = 03 Oct
            | Check-out = 04 Oct
            |
            | date = 03 → tampil
            | date = 04 → tampil
            | date = 05 → tidak tampil
            |
            */

            ->when(
                $request->filled(
                    'date'
                ),

                function (
                    $query
                ) use (
                    $request
                ) {

                    $date =
                        $request->input(
                            'date'
                        );

                    $query->where(function ($query) use ($date) {
                        $query->where(function ($query) use ($date) {
                            $query->whereDate('check_in_date', '<=', $date)
                                ->whereDate('check_out_date', '>', $date);
                        })->orWhereDate('check_out_date', $date);
                    });
                }
            )

            /*
            |--------------------------------------------------------------------------
            | DATE RANGE
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled(
                    'from'
                ),

                fn ($query) => $query->whereDate(
                    'check_in_date',
                    '>=',
                    $request->input(
                        'from'
                    )
                )
            )

            ->when(
                $request->filled(
                    'to'
                ),

                fn ($query) => $query->whereDate(
                    'check_out_date',
                    '<=',
                    $request->input(
                        'to'
                    )
                )
            )

            ->latest()

            ->paginate(
                $perPage
            );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $actor = $request->user();
        $isCustomer = $actor->hasRole('User');

        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'user_id' => 'nullable|exists:users,id',
            'guest_id' => 'nullable|exists:guests,id',
            'guest' => 'nullable|array',
            'guest.full_name' => 'required_with:guest|string|max:120',
            'guest.email' => 'nullable|email|max:255',
            'guest.phone' => 'nullable|string|max:30',
            'guest.address' => 'nullable|string',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'number_of_guests' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'services' => 'nullable|array',
            'services.*.hotel_service_id' => 'required|exists:hotel_services,id',
            'services.*.quantity' => 'required|integer|min:1',
        ]);

        $customerReferences = array_filter([
            ! empty($data['user_id']),
            ! empty($data['guest_id']),
            ! empty($data['guest']['full_name'] ?? null),
        ]);

        if ($isCustomer && count($customerReferences) > 1) {
            throw ValidationException::withMessages([
                'guest' => 'Akun pengguna tidak dapat digabung dengan data tamu walk-in.',
            ]);
        }

        if (! $isCustomer && count($customerReferences) !== 1) {
            throw ValidationException::withMessages([
                'customer' => 'Reservasi harus memiliki tepat satu akun pengguna atau tamu walk-in.',
            ]);
        }

        $targetUser = $isCustomer
            ? $actor
            : (! empty($data['user_id']) ? User::with('role')->findOrFail($data['user_id']) : null);

        if ($isCustomer) {
            $data['user_id'] = $actor->id;
        }

        if (! $isCustomer && $targetUser && ! $targetUser->hasRole('User')) {
            abort(422, 'Reservasi hanya dapat dibuat untuk akun dengan role User.');
        }

        $membership = $targetUser ? Membership::ensureForUser($targetUser) : null;
        // Membership is automatic for every User. The discount applies when
        // the booking reaches the membership minimum of two guests.
        $useMemberDiscount = $membership?->is_active
            && $data['number_of_guests'] >= max((int) $membership->min_guests, Membership::MINIMUM_GUESTS);

        $guestData = $data['guest'] ?? null;
        $services = $data['services'] ?? [];
        unset($data['services']);
        unset($data['guest']);

        $reservation = DB::transaction(function () use ($data, $guestData, $membership, $request, $useMemberDiscount, $services): Reservation {
            if ($guestData) {
                $guestData['created_by'] = $request->user()->id;
                $data['guest_id'] = Guest::create($guestData)->id;
            }

            $room = Room::with('roomType')
                ->lockForUpdate()
                ->findOrFail($data['room_id']);

            abort_if(
                $data['number_of_guests'] > $room->roomType->capacity,
                422,
                'Kapasitas kamar tidak mencukupi.'
            );

            $this->service->ensureAvailable(
                $room->id,
                $data['check_in_date'],
                $data['check_out_date']
            );

            $nights = Carbon::parse($data['check_in_date'])
                ->diffInDays(Carbon::parse($data['check_out_date']));
            $roomPrice = $room->roomType->base_price;
            $serviceTotal = '0.00';

            foreach ($services as $item) {
                $service = HotelService::findOrFail($item['hotel_service_id']);
                $serviceTotal = Money::add($serviceTotal, Money::multiply($service->price, (int) $item['quantity']));
            }

            // Pricing is calculated on the server. The client may request a
            // member discount, but it cannot override tax or totals.
            $settings = HotelSetting::current();
            $taxRate = $settings->tax_rate;
            $serviceChargeRate = $settings->service_charge_rate;
            $memberRate = $useMemberDiscount ? $membership->discount_rate : '0.00';
            $memberDiscount = Money::percent(Money::add(Money::multiply($roomPrice, $nights), $serviceTotal), $memberRate);
            $totals = $this->service->totals(
                $roomPrice,
                $nights,
                $serviceTotal,
                $taxRate,
                $serviceChargeRate,
                $memberDiscount
            );

            $reservation = Reservation::create(array_merge($data, [
                'booking_number' => 'TMP-'.Str::uuid(),
                'membership_id' => $useMemberDiscount ? $membership->id : null,
                'number_of_nights' => $nights,
                'room_price' => $roomPrice,
                'tax_rate' => $taxRate,
                'service_charge_rate' => $serviceChargeRate,
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'service_charge' => $totals['service_charge'],
                'manual_discount' => 0,
                'member_discount_rate' => $memberRate,
                'member_min_guests' => $useMemberDiscount ? $membership->min_guests : 0,
                'member_discount_amount' => $memberDiscount,
                'discount' => $totals['discount'],
                'grand_total' => $totals['grand_total'],
                'status' => $request->user()->hasRole('User') ? ReservationStatus::Pending->value : ReservationStatus::Confirmed->value,
                'created_by' => $request->user()->id,
            ]));

            $reservation->update([
                'booking_number' => 'HTL-'.now()->format('Y').'-'.str_pad((string) $reservation->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($services as $item) {
                $service = HotelService::findOrFail($item['hotel_service_id']);
                $quantity = (int) $item['quantity'];

                $reservation->services()->create([
                    'hotel_service_id' => $service->id,
                    'quantity' => $quantity,
                    'price' => $service->price,
                    'subtotal' => Money::multiply($service->price, $quantity),
                ]);
            }

            $this->service->syncRoomStatus($room->fresh());

            ActivityLog::recordStatusChange($request->user()->id, 'reservation.created', 'reservation', $reservation->id, null, $reservation->status, $reservation->booking_number);

            return $reservation;
        });

        return response()->json($reservation->load([
            'user',
            'guest',
            'room.roomType',
            'membership',
            'services.service',
        ]), 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        return $reservation->load([
            'user',
            'guest',
            'membership',
            'room.roomType',
            'services.service',
            'payments',
            'invoice',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        abort_if(
            $request->user()->hasRole('User') && $this->service->netPaid($reservation) > 0,
            422,
            'Reservasi yang sudah memiliki pembayaran hanya dapat diubah oleh staff.'
        );

        abort_if(
            in_array(
                $reservation->status,
                [
                    ReservationStatus::CheckedIn->value,
                    ReservationStatus::CheckedOut->value,
                    ReservationStatus::Completed->value,
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::NoShow->value,
                ]
            ),

            422,

            'Reservasi sudah tidak dapat diubah.'
        );

        $data = $request->validate([
            'room_id' => 'sometimes|exists:rooms,id',
            'check_in_date' => 'sometimes|date|after_or_equal:today',
            'check_out_date' => 'sometimes|date',
            'number_of_guests' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $reservation) {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $roomId = $data['room_id'] ?? $lockedReservation->room_id;
            $in = $data['check_in_date'] ?? Carbon::parse($lockedReservation->check_in_date)->toDateString();
            $out = $data['check_out_date'] ?? Carbon::parse($lockedReservation->check_out_date)->toDateString();

            abort_unless($out > $in, 422, 'Tanggal check-out harus setelah check-in.');

            $room = Room::with('roomType')->lockForUpdate()->findOrFail($roomId);
            abort_if(in_array($room->status, [RoomStatus::Maintenance->value, RoomStatus::Cleaning->value, RoomStatus::OutOfOrder->value], true), 422, 'Kamar tidak dapat dijual pada status '.$room->status.'.');
            $this->service->ensureAvailable($room->id, $in, $out, $lockedReservation->id);

            $numberOfGuests = $data['number_of_guests'] ?? $lockedReservation->number_of_guests;
            abort_if($numberOfGuests > $room->roomType->capacity, 422, 'Kapasitas kamar tidak mencukupi.');

            if (Money::compare($lockedReservation->member_discount_rate, '0.00') > 0) {
                $minimumMemberGuests = max((int) $lockedReservation->member_min_guests, 2);
                abort_if($numberOfGuests < $minimumMemberGuests, 422, 'Member discount hanya berlaku untuk pesanan minimal '.$minimumMemberGuests.' orang.');
            }

            $oldRoom = Room::lockForUpdate()->findOrFail($lockedReservation->room_id);
            $nights = Carbon::parse($in)->diffInDays(Carbon::parse($out));
            $lockedReservation->update(array_merge($data, [
                'number_of_nights' => $nights,
                'room_price' => $room->roomType->base_price,
            ]));
            $this->service->recalculate($lockedReservation->fresh());

            if ($oldRoom->id !== $room->id) {
                $this->service->syncRoomStatus($oldRoom);
                $this->service->syncRoomStatus($room);
            } else {
                $this->service->syncRoomStatus($room->fresh());
            }

            return $lockedReservation->fresh()->load(['user', 'guest', 'room.roomType', 'membership', 'payments']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM
    |--------------------------------------------------------------------------
    */

    public function confirm(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        return DB::transaction(function () use ($data, $request, $reservation) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_if($reservation->status !== ReservationStatus::Pending->value, 422, 'Hanya reservasi pending yang dapat dikonfirmasi.');
            $this->statusService->transition($reservation, ReservationStatus::Confirmed, $request->user()->id, $data['reason'] ?? null, 'reservation.confirmed');
            $room = $reservation->room()->lockForUpdate()->firstOrFail();
            $this->service->syncRoomStatus($room);

            return $reservation->fresh()->load(['user', 'guest', 'room.roomType', 'payments']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */

    public function cancel(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        abort_if(
            in_array(
                $reservation->status,
                [
                    ReservationStatus::CheckedIn->value,
                    ReservationStatus::CheckedOut->value,
                    ReservationStatus::Completed->value,
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::NoShow->value,
                ]
            ),

            422,

            'Reservasi tidak dapat dibatalkan.'
        );

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $request, $reservation) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_if(in_array($reservation->status, [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value, ReservationStatus::Completed->value, ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value], true), 422, 'Reservasi tidak dapat dibatalkan.');
            $netPaid = $this->service->netPaid($reservation);
            abort_if(
                $request->user()->hasRole('User') && Money::compare($netPaid, '0.00') > 0,
                422,
                'Reservasi berbayar harus dibatalkan oleh staff agar proses refund tercatat.'
            );

            $reservation->update([
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);
            $this->statusService->transition($reservation, ReservationStatus::Cancelled, $request->user()->id, $data['reason'], 'reservation.cancelled');
            $this->service->syncRoomStatus($reservation->room()->lockForUpdate()->firstOrFail());

            $result = $reservation->fresh()->load(['user', 'guest', 'room.roomType', 'payments']);
            $result->setAttribute('refund_due', $netPaid);

            return $result;
        });
    }

    public function noShow(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $request, $reservation) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless(in_array($reservation->status, [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value], true), 422, 'Hanya reservasi aktif yang dapat ditandai no-show.');
            abort_if(Carbon::today()->lt(Carbon::parse($reservation->check_in_date)), 422, 'No-show baru dapat dicatat pada atau setelah tanggal check-in.');

            $reservation->update([
                'no_show_at' => now(),
                'no_show_by' => $request->user()->id,
                'notes' => trim(collect([$reservation->notes, $data['reason'] ?? null])->filter()->implode(PHP_EOL)),
            ]);
            $this->statusService->transition($reservation, ReservationStatus::NoShow, $request->user()->id, $data['reason'] ?? null, 'reservation.no_show');
            $this->service->syncRoomStatus($reservation->room()->lockForUpdate()->firstOrFail());

            return $reservation->fresh()->load(['user', 'guest', 'room.roomType', 'payments']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK-IN
    |--------------------------------------------------------------------------
    |
    | Check-in sesuai jadwal tidak memerlukan override. Check-in lebih awal
    | atau setelah jadwal wajib menyimpan alasan staff pada audit reservasi.
    |
    */

    public function checkIn(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        $data = $request->validate([
            'override_reason' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $request, $reservation) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_if($reservation->status !== ReservationStatus::Confirmed->value, 422, 'Reservasi harus berstatus Confirmed.');

            $checkInDate = Carbon::parse($reservation->check_in_date);
            $checkOutDate = Carbon::parse($reservation->check_out_date);
            $isOutsideSchedule = Carbon::today()->lt($checkInDate) || ! Carbon::today()->lt($checkOutDate);
            abort_if($isOutsideSchedule && empty($data['override_reason']), 422, 'Check-in di luar jadwal membutuhkan alasan override dari staff.');

            $reservation->update([
                'checked_in_at' => now(),
                'arrival_override_reason' => $data['override_reason'] ?? null,
            ]);
            $room = $reservation->room()->lockForUpdate()->firstOrFail();
            $room->update(['status' => RoomStatus::Occupied->value, 'available_rooms' => 0]);

            $this->statusService->transition($reservation, ReservationStatus::CheckedIn, $request->user()->id, $data['override_reason'] ?? null, 'reservation.check_in');

            return $reservation->fresh()->load(['user', 'guest', 'room.roomType', 'payments']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK-OUT
    |--------------------------------------------------------------------------
    |
    | Checkout lebih awal atau terlambat diperbolehkan dengan alasan override.
    | Folio tetap harus lunas agar invoice final dapat diterbitkan.
    |
    */

    public function checkOut(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        $data = $request->validate([
            'override_reason' => 'nullable|string|max:500',
        ]);

        return DB::transaction(
            function () use (
                $data,
                $request,
                $reservation
            ) {

                /*
                |--------------------------------------------------------------------------
                | LOCK RESERVATION
                |--------------------------------------------------------------------------
                */

                $reservation =
                    Reservation::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $reservation
                                ->id
                        );

                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                abort_if(
                    $reservation->status !== ReservationStatus::CheckedIn->value,

                    422,

                    'Hanya reservasi Checked In yang dapat check-out.'
                );

                /*
                |--------------------------------------------------------------------------
                | CHECK-OUT DATE
                |--------------------------------------------------------------------------
                */

                $checkOutDate = Carbon::parse($reservation->check_out_date);
                abort_if(
                    ! $checkOutDate->isToday() && empty($data['override_reason']),
                    422,
                    'Check-out lebih awal atau terlambat membutuhkan alasan override dari staff.'
                );

                /*
                |--------------------------------------------------------------------------
                | PAYMENT
                |--------------------------------------------------------------------------
                */

                $this->service->recalculate($reservation);
                $reservation->refresh();
                $paidAmount = $this->service->netPaid($reservation);
                $balance = $this->service->balance($reservation);
                $balanceForDisplay = intdiv(Money::toCents($balance) + 50, 100);
                abort_if(Money::compare($balance, '0.00') > 0, 422, 'Sisa tagihan '.number_format($balanceForDisplay, 0, ',', '.').' harus diselesaikan sebelum check-out.');

                /*
                |--------------------------------------------------------------------------
                | RESERVATION STATUS
                |--------------------------------------------------------------------------
                */

                $reservation->update([
                    'checked_out_at' => now(),

                    'departure_override_reason' => $data['override_reason'] ?? null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | ROOM AVAILABLE
                |--------------------------------------------------------------------------
                */

                $reservation->room()->update([
                    'status' => RoomStatus::Cleaning->value,
                    'available_rooms' => 0,
                ]);

                /*
                |--------------------------------------------------------------------------
                | INVOICE
                |--------------------------------------------------------------------------
                */

                Invoice::updateOrCreate(

                    [
                        'reservation_id' => $reservation
                            ->id,
                    ],

                    [

                        'invoice_number' => 'INV-'.
                            now()->format(
                                'Ymd'
                            ).
                            '-'.
                            str_pad(
                                (string)
                                    $reservation
                                        ->id,
                                6,
                                '0',
                                STR_PAD_LEFT
                            ),

                        'subtotal' => $reservation
                            ->subtotal,

                        'tax' => $reservation
                            ->tax,

                        'service_charge' => $reservation
                            ->service_charge,

                        'discount' => $reservation
                            ->discount,

                        'grand_total' => $reservation
                            ->grand_total,

                        'paid_total' => $paidAmount,

                        'balance_due' => $balance,

                        'issued_at' => now(),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | ACTIVITY
                |--------------------------------------------------------------------------
                */

                $this->statusService->transition($reservation, ReservationStatus::CheckedOut, $request->user()->id, $data['override_reason'] ?? null, 'reservation.check_out');

                return $reservation
                    ->fresh()
                    ->load([
                        'user',
                        'guest',
                        'room',
                        'services.service',
                        'invoice',
                    ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADD SERVICE
    |--------------------------------------------------------------------------
    */

    public function addService(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        $data =
            $request->validate([

                'hotel_service_id' => 'required|exists:hotel_services,id',

                'quantity' => 'required|integer|min:1',
            ]);

        $serviceModel = HotelService::findOrFail(
            $data[
                'hotel_service_id'
            ]
        );

        $service = DB::transaction(function () use ($data, $request, $reservation, $serviceModel) {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_if(in_array($lockedReservation->status, [ReservationStatus::Cancelled->value, ReservationStatus::CheckedOut->value, ReservationStatus::Completed->value, ReservationStatus::NoShow->value], true), 422, 'Reservasi ini tidak dapat menerima service baru.');

            $service = $lockedReservation->services()->create([
                'hotel_service_id' => $data['hotel_service_id'],
                'quantity' => $data['quantity'],
                'price' => $serviceModel->price,
                'subtotal' => Money::multiply($serviceModel->price, (int) $data['quantity']),
            ]);
            $this->service->recalculate($lockedReservation->fresh());
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'reservation.service_added',
                'entity_type' => 'reservation',
                'entity_id' => $lockedReservation->id,
                'description' => $serviceModel->name.' x '.$data['quantity'],
                'created_at' => now(),
            ]);

            return $service;
        });

        return response()->json(

            $service->load(
                'service'
            ),

            201
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RECALCULATE
    |--------------------------------------------------------------------------
    */

    private function ensureReservationAccess(Request $request, Reservation $reservation): void
    {
        abort_if(
            $request->user()?->hasRole('User') && $reservation->user_id !== $request->user()->id,
            403,
            'Anda hanya dapat mengakses reservasi milik sendiri.'
        );
    }
}
