<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function ensureAvailable(int $roomId, string $checkIn, string $checkOut, ?int $ignoreId = null): void
    {
        $room = Room::findOrFail($roomId);
        if (in_array($room->status, [RoomStatus::Maintenance->value, RoomStatus::Cleaning->value, RoomStatus::OutOfOrder->value], true)) {
            throw ValidationException::withMessages(['room_id' => 'Kamar sedang maintenance.']);
        }
        if ($checkOut <= $checkIn) {
            throw ValidationException::withMessages(['check_out_date' => 'Tanggal check-out harus setelah check-in.']);
        }

        $query = Reservation::where('room_id', $roomId)
            ->whereIn('status', ReservationStatus::active())
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn);
        if ($ignoreId) $query->where('id', '!=', $ignoreId);
        if ($query->exists()) throw ValidationException::withMessages(['room_id' => 'Kamar sudah memiliki reservasi pada periode tersebut.']);
    }

    /** @return array{room_cost:string, subtotal:string, tax:string, service_charge:string, discount:string, grand_total:string} */
    public function totals(mixed $roomPrice, int $nights, mixed $services = '0', mixed $taxRate = '0', mixed $serviceChargeRate = '0', mixed $discount = '0'): array
    {
        $roomCost = Money::multiply($roomPrice, $nights);
        $subtotal = Money::add($roomCost, $services);
        $discount = Money::min(Money::max($discount, '0.00'), $subtotal);
        $discountedSubtotal = Money::max(Money::subtract($subtotal, $discount), '0.00');
        $serviceCharge = Money::percent($discountedSubtotal, $serviceChargeRate);
        $tax = Money::percent(Money::add($discountedSubtotal, $serviceCharge), $taxRate);

        return [
            'room_cost' => $roomCost,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'service_charge' => $serviceCharge,
            'discount' => $discount,
            'grand_total' => Money::max(Money::add($discountedSubtotal, $serviceCharge, $tax), '0.00'),
        ];
    }

    public function recalculate(Reservation $reservation): void
    {
        $serviceTotal = Money::normalize($reservation->services()->sum('subtotal'));
        $subtotal = Money::add(Money::multiply($reservation->room_price, (int) $reservation->number_of_nights), $serviceTotal);
        $memberDiscount = Money::percent($subtotal, $reservation->member_discount_rate);
        $manualDiscount = Money::max($reservation->manual_discount, '0.00');
        $totalDiscount = Money::min(Money::add($manualDiscount, $memberDiscount), $subtotal);
        $discountedSubtotal = Money::max(Money::subtract($subtotal, $totalDiscount), '0.00');
        $serviceCharge = Money::percent($discountedSubtotal, $reservation->service_charge_rate);
        $tax = Money::percent(Money::add($discountedSubtotal, $serviceCharge), $reservation->tax_rate);

        $reservation->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'service_charge' => $serviceCharge,
            'discount' => $totalDiscount,
            'member_discount_amount' => $memberDiscount,
            'grand_total' => Money::max(Money::add($discountedSubtotal, $serviceCharge, $tax), '0.00'),
        ]);
    }

    public function netPaid(Reservation $reservation): string
    {
        $payments = $reservation->payments()->where('transaction_type', 'Payment')->where('payment_status', 'Paid')->sum('amount');
        $refunds = $reservation->payments()->where('transaction_type', 'Refund')->where('payment_status', 'Refunded')->sum('amount');

        return Money::subtract($payments, $refunds);
    }

    public function balance(Reservation $reservation): string
    {
        return Money::max(Money::subtract($reservation->grand_total, $this->netPaid($reservation)), '0.00');
    }

    public function refundableAmount(Payment $payment): string
    {
        if ($payment->transaction_type !== 'Payment' || $payment->payment_status !== 'Paid') return '0.00';
        $refunded = $payment->refunds()->where('transaction_type', 'Refund')->where('payment_status', 'Refunded')->sum('amount');

        return Money::max(Money::subtract($payment->amount, $refunded), '0.00');
    }

    public function syncRoomStatus(Room $room): void
    {
        if (in_array($room->status, [RoomStatus::Maintenance->value, RoomStatus::Cleaning->value, RoomStatus::OutOfOrder->value], true)) {
            $room->update(['available_rooms' => 0]);
            return;
        }

        $today = Carbon::today()->toDateString();
        $activeReservation = $room->reservations()
            ->whereIn('status', ReservationStatus::active())
            ->whereDate('check_in_date', '<=', $today)
            ->whereDate('check_out_date', '>', $today)
            ->orderByRaw("CASE WHEN status = '".ReservationStatus::CheckedIn->value."' THEN 0 ELSE 1 END")
            ->first();

        $status = match ($activeReservation?->status) {
            ReservationStatus::CheckedIn->value => RoomStatus::Occupied->value,
            ReservationStatus::Pending->value, ReservationStatus::Confirmed->value => RoomStatus::Reserved->value,
            default => RoomStatus::Available->value,
        };

        $room->update([
            'status' => $status,
            'available_rooms' => $status === RoomStatus::Available->value ? 1 : 0,
        ]);
    }
}
