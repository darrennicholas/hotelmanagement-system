<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function reservations(Request $request) { return Reservation::with(['user', 'room.roomType'])->when($request->user()?->hasRole('User'), fn($q) => $q->where('user_id', $request->user()->id))->when($request->from, fn($q, $v) => $q->whereDate('check_in_date', '>=', $v))->when($request->to, fn($q, $v) => $q->whereDate('check_out_date', '<=', $v))->when($request->status, fn($q, $v) => $q->where('status', $v))->get(); }
    public function revenue(Request $request) { $from = Carbon::parse($request->input('from', now()->startOfMonth())); $to = Carbon::parse($request->input('to', now()->endOfDay())); return Payment::whereBetween('paid_at', [$from, $to])->where('payment_status', 'Paid')->when($request->user()?->hasRole('User'), fn($q) => $q->whereHas('reservation', fn($reservation) => $reservation->where('user_id', $request->user()->id)))->selectRaw('DATE(paid_at) as date, SUM(amount) as total')->groupBy('date')->orderBy('date')->get(); }
    public function occupancy(Request $request) { $total = \App\Models\Room::count(); $occupied = \App\Models\Room::where('status', 'Occupied')->count(); return ['total_rooms' => $total, 'occupied_rooms' => $occupied, 'available_rooms' => \App\Models\Room::where('status', 'Available')->count(), 'occupancy_rate' => $total ? round($occupied / $total * 100, 1) : 0]; }
    public function payments(Request $request) { return Payment::with(['reservation.user'])->when($request->user()?->hasRole('User'), fn($q) => $q->whereHas('reservation', fn($reservation) => $reservation->where('user_id', $request->user()->id)))->when($request->from, fn($q, $v) => $q->whereDate('paid_at', '>=', $v))->when($request->to, fn($q, $v) => $q->whereDate('paid_at', '<=', $v))->when($request->method, fn($q, $v) => $q->where('payment_method', $v))->get(); }
}
