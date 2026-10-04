<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

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
            ->where('status', 'Available')
            ->where('available_rooms', '>', 0)
            ->when($data['room_type'] ?? null, fn ($q, $v) => $q->where('room_type_id', $v))
            ->whereHas('roomType', fn ($q) => $q->when($data['guests'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', $v)))
            ->whereDoesntHave('reservations', fn ($q) => $q
                ->whereIn('status', ['Pending', 'Confirmed', 'Checked In'])
                ->where('check_in_date', '<', $data['check_out'])
                ->where('check_out_date', '>', $data['check_in']))
            ->get();
    }
}
