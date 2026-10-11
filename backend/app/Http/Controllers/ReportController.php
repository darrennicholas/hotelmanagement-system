<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\HotelMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function reservations(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'status' => ['nullable', Rule::enum(ReservationStatus::class)]]);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->endOfMonth()->toDateString();
        $toExclusive = Carbon::parse($to)->addDay()->toDateString();

        return Reservation::with(['user', 'guest', 'room.roomType'])
            ->when($request->user()?->hasRole('User'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereDate('check_in_date', '<', $toExclusive)
            ->whereDate('check_out_date', '>', $from)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->get();
    }

    public function revenue(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'method' => 'nullable|string']);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth());
        $to = Carbon::parse($data['to'] ?? now()->endOfDay())->endOfDay();

        return Payment::whereBetween('paid_at', [$from, $to])
            ->whereIn('payment_status', ['Paid', 'Refunded'])
            ->when($data['method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($request->user()?->hasRole('User'), fn ($query) => $query->whereHas('reservation', fn ($reservation) => $reservation->where('user_id', $request->user()->id)))
            ->selectRaw("DATE(paid_at) as date, SUM(CASE WHEN transaction_type = 'Refund' THEN -amount ELSE amount END) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function occupancy(Request $request, HotelMetricsService $metrics)
    {
        $data = $request->validate(['date' => 'nullable|date']);
        $date = Carbon::parse($data['date'] ?? now()->toDateString());
        $snapshot = $metrics->roomSnapshot($date);

        return [
            'date' => $date->toDateString(),
            'total_rooms' => $snapshot['total'],
            'occupied_rooms' => $snapshot['occupied'],
            'reserved_rooms' => $snapshot['reserved'],
            'available_rooms' => $snapshot['available'],
            'cleaning_rooms' => $snapshot['cleaning'],
            'maintenance_rooms' => $snapshot['maintenance'],
            'out_of_order_rooms' => $snapshot['out_of_order'],
            'occupancy_rate' => $snapshot['occupancy_rate'],
        ];
    }

    public function payments(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'method' => 'nullable|string']);

        return Payment::with(['reservation.user', 'reservation.guest'])->when($request->user()?->hasRole('User'), fn ($q) => $q->whereHas('reservation', fn ($reservation) => $reservation->where('user_id', $request->user()->id)))->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))->when($data['method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))->get();
    }
}
