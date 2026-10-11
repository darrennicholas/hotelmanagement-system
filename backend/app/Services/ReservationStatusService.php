<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;

final class ReservationStatusService
{
    public function transition(Reservation $reservation, ReservationStatus $newStatus, int $actorId, ?string $reason = null, ?string $action = null): Reservation
    {
        $oldStatus = $reservation->status;
        if ($oldStatus === $newStatus->value) return $reservation;

        $reservation->update(['status' => $newStatus->value]);
        ActivityLog::recordStatusChange($actorId, $action ?? 'reservation.'.strtolower(str_replace(' ', '_', $newStatus->value)), 'reservation', $reservation->id, $oldStatus, $newStatus->value, $reason);

        return $reservation;
    }
}
