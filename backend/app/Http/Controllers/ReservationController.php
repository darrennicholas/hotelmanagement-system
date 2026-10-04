<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HotelService;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $service
    ) {}


    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $request->validate([
            'search' =>
                'nullable|string',

            'status' =>
                'nullable|string',

            'date' =>
                'nullable|date_format:Y-m-d',

            'from' =>
                'nullable|date',

            'to' =>
                'nullable|date',

            'per_page' =>
                'nullable|integer|min:1|max:100',
        ]);


        $perPage =
            $request->integer(
                'per_page',
                15
            );


        return Reservation::with([
            'user',
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

                fn ($query) =>
                    $query->where(
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


                    $query
                        ->whereDate(
                            'check_in_date',
                            '<=',
                            $date
                        )

                        ->whereDate(
                            'check_out_date',
                            '>=',
                            $date
                        );
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

                fn ($query) =>
                    $query->whereDate(
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

                fn ($query) =>
                    $query->whereDate(
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

    public function store(
        Request $request
    ) {

        abort_unless(
            $request->user()?->hasRole('User'),
            403,
            'Hanya user yang dapat membuat reservasi.'
        );

        $data =
            $request->validate([

                'user_id' =>
                    $request->user()->hasRole('User')
                        ? 'nullable|exists:users,id'
                        : 'required|exists:users,id',

                'room_id' =>
                    'required|exists:rooms,id',

                'check_in_date' =>
                    'required|date|after_or_equal:today',

                'check_out_date' =>
                    'required|date|after:check_in_date',

                'number_of_guests' =>
                    'required|integer|min:1',

                'notes' =>
                    'nullable|string',

                'tax_rate' =>
                    'nullable|numeric|min:0|max:100',

                'discount' =>
                    'nullable|numeric|min:0',
            ]);

        if ($request->user()->hasRole('User')) {
            $data['user_id'] = $request->user()->id;
        }


        $room =
            Room::with(
                'roomType'
            )->findOrFail(
                $data['room_id']
            );


        abort_if(
            $data[
                'number_of_guests'
            ] >
            $room
                ->roomType
                ->capacity,

            422,

            'Kapasitas kamar tidak mencukupi.'
        );


        $this
            ->service
            ->ensureAvailable(
                $room->id,
                $data[
                    'check_in_date'
                ],
                $data[
                    'check_out_date'
                ]
            );


        $nights =
            Carbon::parse(
                $data[
                    'check_in_date'
                ]
            )
            ->diffInDays(
                Carbon::parse(
                    $data[
                        'check_out_date'
                    ]
                )
            );


        $totals =
            $this
                ->service
                ->totals(

                    (float)
                        $room
                            ->roomType
                            ->base_price,

                    $nights,

                    0,

                    (float)
                        (
                            $data[
                                'tax_rate'
                            ] ??
                            0
                        ),

                    (float)
                        (
                            $data[
                                'discount'
                            ] ??
                            0
                        )
                );


        $reservation =
            DB::transaction(
                function () use (
                    $data,
                    $room,
                    $nights,
                    $totals,
                    $request
                ) {

                    $reservation =
                        Reservation::create(
                            array_merge(
                                $data,
                                [

                                    'booking_number' =>
                                        'HTL-' .
                                        now()->format(
                                            'Y'
                                        ) .
                                        '-' .
                                        str_pad(
                                            (string)
                                                (
                                                    Reservation::max(
                                                        'id'
                                                    ) +
                                                    1
                                                ),
                                            6,
                                            '0',
                                            STR_PAD_LEFT
                                        ),

                                    'number_of_nights' =>
                                        $nights,

                                    'room_price' =>
                                        $room
                                            ->roomType
                                            ->base_price,

                                    'subtotal' =>
                                        $totals[
                                            'subtotal'
                                        ],

                                    'tax' =>
                                        $totals[
                                            'tax'
                                        ],

                                    'discount' =>
                                        $totals[
                                            'discount'
                                        ],

                                    'grand_total' =>
                                        $totals[
                                            'grand_total'
                                        ],

                                    'status' =>
                                        'Confirmed',

                                    'created_by' =>
                                        $request
                                            ->user()
                                            ->id,
                                ]
                            )
                        );


                    $room->update([
                        'status' =>
                            'Reserved',

                        'available_rooms' =>
                            0,
                    ]);


                    ActivityLog::create([

                        'user_id' =>
                            $request
                                ->user()
                                ->id,

                        'action' =>
                            'reservation.created',

                        'entity_type' =>
                            'reservation',

                        'entity_id' =>
                            $reservation
                                ->id,

                        'description' =>
                            $reservation
                                ->booking_number,
                    ]);


                    return $reservation;
                }
            );


        return response()->json(

            $reservation->load([
                'user',
                'room.roomType',
            ]),

            201
        );
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
            in_array(
                $reservation->status,
                [
                    'Checked Out',
                    'Completed',
                    'Cancelled',
                ]
            ),

            422,

            'Reservasi sudah tidak dapat diubah.'
        );


        $data =
            $request->validate([

                'room_id' =>
                    'sometimes|exists:rooms,id',

                'check_in_date' =>
                    'sometimes|date',

                'check_out_date' =>
                    'sometimes|date',

                'number_of_guests' =>
                    'sometimes|integer|min:1',

                'notes' =>
                    'nullable|string',
            ]);


        $roomId =
            $data[
                'room_id'
            ] ??
            $reservation
                ->room_id;


        $in =
            $data[
                'check_in_date'
            ] ??
            Carbon::parse(
                $reservation
                    ->check_in_date
            )->toDateString();


        $out =
            $data[
                'check_out_date'
            ] ??
            Carbon::parse(
                $reservation
                    ->check_out_date
            )->toDateString();


        if ($out <= $in) {

            return response()->json([
                'message' =>
                    'Tanggal check-out harus setelah check-in.',
            ], 422);
        }


        $this
            ->service
            ->ensureAvailable(
                $roomId,
                $in,
                $out,
                $reservation->id
            );


        $room =
            Room::with(
                'roomType'
            )->findOrFail(
                $roomId
            );


        $numberOfGuests =
            $data[
                'number_of_guests'
            ] ??
            $reservation
                ->number_of_guests;


        abort_if(
            $numberOfGuests >
            $room
                ->roomType
                ->capacity,

            422,

            'Kapasitas kamar tidak mencukupi.'
        );


        $nights =
            Carbon::parse(
                $in
            )->diffInDays(
                Carbon::parse(
                    $out
                )
            );


        $totals =
            $this
                ->service
                ->totals(

                    (float)
                        $room
                            ->roomType
                            ->base_price,

                    $nights
                );


        $oldRoom =
            $reservation
                ->room;


        $reservation->update(
            array_merge(
                $data,
                [

                    'number_of_nights' =>
                        $nights,

                    'room_price' =>
                        $room
                            ->roomType
                            ->base_price,

                    'subtotal' =>
                        $totals[
                            'subtotal'
                        ],

                    'grand_total' =>
                        $totals[
                            'grand_total'
                        ],
                ]
            )
        );


        if (
            $oldRoom->id !==
            $room->id
        ) {

            $oldRoom->update([
                'status' =>
                    'Available',

                'available_rooms' =>
                    1,
            ]);


            $room->update([
                'status' =>
                    'Reserved',

                'available_rooms' =>
                    0,
            ]);
        }


        return $reservation
            ->fresh()
            ->load([
                'user',
                'room.roomType',
            ]);
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

        abort_if(
            $reservation->status !==
                'Pending',

            422,

            'Hanya reservasi pending yang dapat dikonfirmasi.'
        );


        $reservation->update([
            'status' =>
                'Confirmed',
        ]);


        $reservation
            ->room()
            ->update([

                'status' =>
                    'Reserved',

                'available_rooms' =>
                    0,
            ]);


        return $reservation
            ->fresh()
            ->load([
                'user',
                'room',
            ]);
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
                    'Checked Out',
                    'Completed',
                    'Cancelled',
                ]
            ),

            422,

            'Reservasi tidak dapat dibatalkan.'
        );


        $reservation->update([

            'status' =>
                'Cancelled',

            'cancelled_by' =>
                $request
                    ->user()
                    ->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                $request->input(
                    'reason'
                ),
        ]);


        $reservation
            ->room()
            ->update([

                'status' =>
                    'Available',

                'available_rooms' =>
                    1,
            ]);


        return $reservation;
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK-IN
    |--------------------------------------------------------------------------
    |
    | Check-in hanya dapat dilakukan
    | tepat pada check_in_date.
    |
    */

    public function checkIn(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        abort_if(
            $reservation->status !==
                'Confirmed',

            422,

            'Reservasi harus berstatus Confirmed.'
        );


        /*
        |--------------------------------------------------------------------------
        | DATE
        |--------------------------------------------------------------------------
        */

        $checkInDate =
            Carbon::parse(
                $reservation
                    ->check_in_date
            );


        abort_unless(
            $checkInDate->isToday(),

            422,

            'Check-in hanya dapat dilakukan pada tanggal ' .
                $checkInDate->format(
                    'd-m-Y'
                ) .
                '.'
        );


        /*
        |--------------------------------------------------------------------------
        | PAYMENT
        |--------------------------------------------------------------------------
        */

        $paidAmount =
            (float)
            $reservation
                ->payments()
                ->where(
                    'payment_status',
                    'Paid'
                )
                ->sum(
                    'amount'
                );


        abort_unless(
            $paidAmount >=
            (float)
                $reservation
                    ->grand_total,

            422,

            'Pembayaran harus diselesaikan sebelum check-in.'
        );


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        $reservation->update([

            'status' =>
                'Checked In',

            'checked_in_at' =>
                now(),
        ]);


        $reservation
            ->room()
            ->update([

                'status' =>
                    'Occupied',

                'available_rooms' =>
                    0,
            ]);


        ActivityLog::create([

            'user_id' =>
                $request
                    ->user()
                    ->id,

            'action' =>
                'reservation.check_in',

            'entity_type' =>
                'reservation',

            'entity_id' =>
                $reservation
                    ->id,
        ]);


        return $reservation
            ->fresh()
            ->load([
                'user',
                'room',
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK-OUT
    |--------------------------------------------------------------------------
    |
    | PERUBAHAN UTAMA:
    |
    | Checkout hanya dapat dilakukan
    | tepat pada tanggal check_out_date.
    |
    | Contoh:
    |
    | check_out_date = 2026-10-04
    |
    | 03 Oktober -> DITOLAK
    | 04 Oktober -> BOLEH
    | 05 Oktober -> DITOLAK
    |
    */

    public function checkOut(
        Request $request,
        Reservation $reservation
    ) {
        $this->ensureReservationAccess($request, $reservation);

        return DB::transaction(
            function () use (
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
                    $reservation->status !==
                        'Checked In',

                    422,

                    'Hanya reservasi Checked In yang dapat check-out.'
                );


                /*
                |--------------------------------------------------------------------------
                | CHECK-OUT DATE
                |--------------------------------------------------------------------------
                */

                $checkOutDate =
                    Carbon::parse(
                        $reservation
                            ->check_out_date
                    );


                abort_unless(
                    $checkOutDate->isToday(),

                    422,

                    'Check-out hanya dapat dilakukan pada tanggal ' .
                        $checkOutDate->format(
                            'd-m-Y'
                        ) .
                        '.'
                );


                /*
                |--------------------------------------------------------------------------
                | PAYMENT
                |--------------------------------------------------------------------------
                */

                $paidAmount =
                    (float)
                    $reservation
                        ->payments()
                        ->where(
                            'payment_status',
                            'Paid'
                        )
                        ->sum(
                            'amount'
                        );


                abort_unless(
                    $paidAmount >=
                    (float)
                        $reservation
                            ->grand_total,

                    422,

                    'Pembayaran harus diselesaikan sebelum check-out.'
                );


                /*
                |--------------------------------------------------------------------------
                | RECALCULATE
                |--------------------------------------------------------------------------
                */

                $this->recalculate(
                    $reservation
                );


                /*
                |--------------------------------------------------------------------------
                | RESERVATION STATUS
                |--------------------------------------------------------------------------
                */

                $reservation->update([

                    'status' =>
                        'Checked Out',

                    'checked_out_at' =>
                        now(),
                ]);


                /*
                |--------------------------------------------------------------------------
                | ROOM AVAILABLE
                |--------------------------------------------------------------------------
                */

                $reservation
                    ->room()
                    ->update([

                        'status' =>
                            'Available',

                        'available_rooms' =>
                            1,
                    ]);


                /*
                |--------------------------------------------------------------------------
                | INVOICE
                |--------------------------------------------------------------------------
                */

                Invoice::updateOrCreate(

                    [
                        'reservation_id' =>
                            $reservation
                                ->id,
                    ],

                    [

                        'invoice_number' =>
                            'INV-' .
                            now()->format(
                                'Ymd'
                            ) .
                            '-' .
                            str_pad(
                                (string)
                                    $reservation
                                        ->id,
                                6,
                                '0',
                                STR_PAD_LEFT
                            ),

                        'subtotal' =>
                            $reservation
                                ->subtotal,

                        'tax' =>
                            $reservation
                                ->tax,

                        'discount' =>
                            $reservation
                                ->discount,

                        'grand_total' =>
                            $reservation
                                ->grand_total,

                        'issued_at' =>
                            now(),
                    ]
                );


                /*
                |--------------------------------------------------------------------------
                | ACTIVITY
                |--------------------------------------------------------------------------
                */

                ActivityLog::create([

                    'user_id' =>
                        $request
                            ->user()
                            ->id,

                    'action' =>
                        'reservation.check_out',

                    'entity_type' =>
                        'reservation',

                    'entity_id' =>
                        $reservation
                            ->id,
                ]);


                return $reservation
                    ->fresh()
                    ->load([
                        'user',
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

                'hotel_service_id' =>
                    'required|exists:hotel_services,id',

                'quantity' =>
                    'required|integer|min:1',
            ]);


        $serviceModel =
            HotelService::findOrFail(
                $data[
                    'hotel_service_id'
                ]
            );


        $service =
            $reservation
                ->services()
                ->create([

                    'hotel_service_id' =>
                        $data[
                            'hotel_service_id'
                        ],

                    'quantity' =>
                        $data[
                            'quantity'
                        ],

                    'price' =>
                        $serviceModel
                            ->price,

                    'subtotal' =>
                        $serviceModel
                            ->price *
                        $data[
                            'quantity'
                        ],
                ]);


        $this->recalculate(
            $reservation
        );


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

    private function recalculate(
        Reservation $reservation
    ): void {

        $serviceTotal =
            $reservation
                ->services()
                ->sum(
                    'subtotal'
                );


        $totals =
            $this
                ->service
                ->totals(

                    (float)
                        $reservation
                            ->room_price,

                    (int)
                        $reservation
                            ->number_of_nights,

                    (float)
                        $serviceTotal,

                    0,

                    (float)
                        $reservation
                            ->discount
                );


        $reservation->update([

            'subtotal' =>
                $totals[
                    'subtotal'
                ],

            'tax' =>
                $reservation
                    ->tax,

            'grand_total' =>
                $totals[
                    'subtotal'
                ] +
                (float)
                    $reservation
                        ->tax -
                (float)
                    $reservation
                        ->discount,
        ]);
    }

    private function ensureReservationAccess(Request $request, Reservation $reservation): void
    {
        abort_if(
            $request->user()?->hasRole('User') && $reservation->user_id !== $request->user()->id,
            403,
            'Anda hanya dapat mengakses reservasi milik sendiri.'
        );
    }
}
