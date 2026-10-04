<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['date' => 'nullable|date']);
        $selectedDate = Carbon::parse($data['date'] ?? now()->toDateString());
        $rooms = Room::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $revenue = Payment::whereDate('paid_at', $selectedDate)->where('payment_status', 'Paid')->sum('amount');
        $monthly = Payment::whereMonth('paid_at', $selectedDate->month)->whereYear('paid_at', $selectedDate->year)->where('payment_status', 'Paid')->sum('amount');

        return response()->json(['rooms' => ['total' => Room::count(), 'available' => (int) Room::where('status', 'Available')->sum('available_rooms'), 'occupied' => (int) ($rooms['Occupied'] ?? 0), 'reserved' => (int) ($rooms['Reserved'] ?? 0), 'maintenance' => (int) ($rooms['Maintenance'] ?? 0)], 'reservations' => ['today' => Reservation::whereDate('created_at', $selectedDate)->count(), 'check_ins' => Reservation::whereDate('check_in_date', $selectedDate)->whereIn('status', ['Confirmed', 'Checked In'])->count(), 'check_outs' => Reservation::whereDate('check_out_date', $selectedDate)->whereIn('status', ['Checked In', 'Checked Out', 'Completed'])->count(), 'cancelled' => Reservation::whereDate('updated_at', $selectedDate)->where('status', 'Cancelled')->count()], 'financial' => ['today' => (float) $revenue, 'month' => (float) $monthly], 'occupancy_rate' => Room::count() ? round(((int) ($rooms['Occupied'] ?? 0) / Room::count()) * 100, 1) : 0]);
    }
}
