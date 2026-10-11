<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\HotelMetricsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HotelMetricsService $metrics)
    {
        $data = $request->validate(['date' => 'nullable|date']);
        $selectedDate = Carbon::parse($data['date'] ?? now()->toDateString());
        $roomSnapshot = $metrics->roomSnapshot($selectedDate);
        $netExpression = DB::raw("CASE WHEN transaction_type = 'Refund' THEN -amount ELSE amount END");
        $revenue = Payment::whereDate('paid_at', $selectedDate)->whereIn('payment_status', ['Paid', 'Refunded'])->sum($netExpression);
        $monthly = Payment::whereMonth('paid_at', $selectedDate->month)->whereYear('paid_at', $selectedDate->year)->whereIn('payment_status', ['Paid', 'Refunded'])->sum($netExpression);

        return response()->json([
            'date' => $selectedDate->toDateString(),
            'rooms' => $roomSnapshot,
            'reservations' => [
                'today' => Reservation::whereDate('created_at', $selectedDate)->count(),
                'check_ins' => Reservation::whereDate('check_in_date', $selectedDate)->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value])->count(),
                'check_outs' => Reservation::whereDate('check_out_date', $selectedDate)->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value, ReservationStatus::Completed->value])->count(),
                'cancelled' => Reservation::whereDate('cancelled_at', $selectedDate)->where('status', ReservationStatus::Cancelled->value)->count(),
            ],
            'financial' => ['today' => Money::normalize($revenue), 'month' => Money::normalize($monthly)],
            'occupancy_rate' => $roomSnapshot['occupancy_rate'],
        ]);
    }
}
