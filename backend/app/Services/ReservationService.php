<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function ensureAvailable(int $roomId, string $checkIn, string $checkOut, ?int $ignoreId = null): void
    {
        $room = Room::findOrFail($roomId);
        if ($room->status === 'Maintenance') {
            throw ValidationException::withMessages(['room_id' => 'Kamar sedang maintenance.']);
        }
        if ($checkOut <= $checkIn) {
            throw ValidationException::withMessages(['check_out_date' => 'Tanggal check-out harus setelah check-in.']);
        }
        $query = Reservation::where('room_id', $roomId)
            ->whereIn('status', ['Pending', 'Confirmed', 'Checked In'])
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn);
        if ($ignoreId) $query->where('id', '!=', $ignoreId);
        if ($query->exists()) {
            throw ValidationException::withMessages(['room_id' => 'Kamar sudah memiliki reservasi pada periode tersebut.']);
        }
    }

    public function totals(float $roomPrice, int $nights, float $services = 0, float $taxRate = 0, float $discount = 0): array
    {
        $roomCost = $roomPrice * $nights;
        $subtotal = $roomCost + $services;
        $tax = round($subtotal * ($taxRate / 100), 2);
        return ['room_cost' => $roomCost, 'subtotal' => $subtotal, 'tax' => $tax, 'discount' => $discount, 'grand_total' => $subtotal + $tax - $discount];
    }

    public function recalculate(Reservation $reservation): void
    {
        $serviceTotal = (float) $reservation->services()->sum('subtotal');
        $subtotal = ((float) $reservation->room_price * (int) $reservation->number_of_nights) + $serviceTotal;

        $reservation->update([
            'subtotal' => $subtotal,
            'grand_total' => $subtotal + (float) $reservation->tax - (float) $reservation->discount,
        ]);
    }
}
