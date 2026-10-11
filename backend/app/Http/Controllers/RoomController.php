<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        return Room::with('roomType')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where('room_number', 'like', "%$v%"))
            ->orderBy('room_number')
            ->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_number' => 'required|string|unique:rooms,room_number',
            'room_type_id' => 'required|exists:room_types,id',
            'floor' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);
        $data['status'] = 'Available';
        $data['available_rooms'] = 1;

        return response()->json(Room::create($data)->load('roomType'), 201);
    }

    public function show(Room $room)
    {
        return $room->load('roomType');
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'room_number' => 'sometimes|string|unique:rooms,room_number,'.$room->id,
            'room_type_id' => 'sometimes|exists:room_types,id',
            'floor' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);
        $room->update($data);

        return $room->fresh()->load('roomType');
    }

    public function updateStatus(Request $request, Room $room, ReservationService $reservationService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(RoomStatus::class)],
        ]);

        abort_if(in_array($room->status, [RoomStatus::Reserved->value, RoomStatus::Occupied->value], true), 422, 'Status kamar aktif dikelola oleh proses reservasi.');

        if ($data['status'] === 'Available') {
            $hasActiveReservation = $room->reservations()
                ->whereIn('status', ReservationStatus::active())
                ->whereDate('check_in_date', '<=', now()->toDateString())
                ->whereDate('check_out_date', '>', now()->toDateString())
                ->exists();

            abort_if($hasActiveReservation, 422, 'Kamar masih terikat reservasi aktif.');
        }

        $room->update([
            'status' => $data['status'],
            'available_rooms' => $data['status'] === 'Available' ? 1 : 0,
        ]);

        if ($data['status'] === 'Available') {
            $reservationService->syncRoomStatus($room->fresh());
        }

        return $room->fresh()->load('roomType');
    }

    public function destroy(Room $room)
    {
        abort_if($room->reservations()->exists(), 422, 'Kamar memiliki riwayat transaksi.');
        $room->delete();

        return response()->noContent();
    }

    public function availability(Request $request)
    {
        $data = $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'room_type' => 'nullable|exists:room_types,id',
            'guests' => 'nullable|integer|min:1',
        ]);

        return Room::with('roomType')
            // Status describes the room's current operational state. A room
            // reserved for next week can still be available for a different
            // non-overlapping date range, so availability must be decided by
            // reservations, not by the current status flag alone.
            ->whereNotIn('status', [RoomStatus::Maintenance->value, RoomStatus::Cleaning->value, RoomStatus::OutOfOrder->value])
            ->when($data['room_type'] ?? null, fn ($q, $v) => $q->where('room_type_id', $v))
            ->whereHas('roomType', fn ($q) => $q->when($data['guests'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', $v)))
            ->whereDoesntHave('reservations', fn ($q) => $q
                ->whereIn('status', ReservationStatus::active())
                ->where('check_in_date', '<', $data['check_out'])
                ->where('check_out_date', '>', $data['check_in']))
            ->orderBy('room_number')
            ->get();
    }
}
