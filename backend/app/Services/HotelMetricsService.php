<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Carbon;

class HotelMetricsService
{
    /**
     * Return a date-aware room snapshot used by the dashboard and reports.
     * A checkout date is exclusive: the room is not occupied on that date.
     *
     * @return array{total:int, available:int, occupied:int, reserved:int, cleaning:int, maintenance:int, out_of_order:int, occupancy_rate:float}
     */
    public function roomSnapshot(Carbon $date): array
    {
        $statusCounts = Room::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $reservations = Reservation::query()
            ->whereNotIn('status', [ReservationStatus::Cancelled->value])
            ->whereDate('check_in_date', '<=', $date)
            ->whereDate('check_out_date', '>', $date)
            ->get(['room_id', 'status']);

        $occupied = $reservations
            ->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value, ReservationStatus::Completed->value])
            ->pluck('room_id')
            ->unique()
            ->count();
        $reserved = $reservations
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
            ->pluck('room_id')
            ->unique()
            ->count();
        $total = (int) Room::count();
        $cleaning = (int) ($statusCounts['Cleaning'] ?? 0);
        $maintenance = (int) ($statusCounts['Maintenance'] ?? 0);
        $outOfOrder = (int) ($statusCounts['Out of Order'] ?? 0);
        $unavailable = $cleaning + $maintenance + $outOfOrder;
        $available = max($total - $occupied - $reserved - $unavailable, 0);
        $sellableRooms = max($total - $unavailable, 0);

        return [
            'total' => $total,
            'available' => $available,
            'occupied' => $occupied,
            'reserved' => $reserved,
            'cleaning' => $cleaning,
            'maintenance' => $maintenance,
            'out_of_order' => $outOfOrder,
            'occupancy_rate' => $sellableRooms > 0
                ? round((($occupied + $reserved) / $sellableRooms) * 100, 1)
                : 0,
        ];
    }
}
