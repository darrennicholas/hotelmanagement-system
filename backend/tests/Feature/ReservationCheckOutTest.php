<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationCheckOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_makes_room_available_for_a_new_reservation(): void
    {
        $user = User::factory()->create();
        $roomType = RoomType::create(['name' => 'Deluxe Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '301', 'room_type_id' => $roomType->id, 'floor' => 3, 'status' => 'Occupied', 'available_rooms' => 0]);
        $customer = User::factory()->create(['name' => 'Customer One']);
        $reservation = Reservation::create([
            'booking_number' => 'HTL-2026-000001',
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::tomorrow(),
            'number_of_guests' => 1,
            'number_of_nights' => 1,
            'room_price' => 100000,
            'subtotal' => 100000,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => 100000,
            'status' => 'Checked In',
            'created_by' => $user->id,
            'checked_in_at' => now(),
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'payment_number' => 'PAY-202610030001',
            'payment_method' => 'Cash',
            'amount' => 100000,
            'paid_amount' => 100000,
            'change_amount' => 0,
            'payment_status' => 'Paid',
            'paid_at' => now(),
            'processed_by' => $user->id,
        ]);

        $checkout = $this->actingAs($user, 'sanctum')->postJson('/api/reservations/'.$reservation->id.'/check-out');

        $checkout->assertOk()->assertJsonPath('room.status', 'Available');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'Available', 'available_rooms' => 1]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'Checked Out']);

        $newCustomer = User::factory()->create(['name' => 'Customer Two']);
        $newReservation = $this->postJson('/api/reservations', [
            'user_id' => $newCustomer->id,
            'room_id' => $room->id,
            'check_in_date' => Carbon::tomorrow()->toDateString(),
            'check_out_date' => Carbon::tomorrow()->addDay()->toDateString(),
            'number_of_guests' => 1,
        ]);

        $newReservation->assertCreated();
        $this->assertDatabaseHas('reservations', ['user_id' => $newCustomer->id, 'room_id' => $room->id, 'status' => 'Confirmed']);
    }

    public function test_checkin_requires_a_paid_reservation(): void
    {
        $user = User::factory()->create();
        $roomType = RoomType::create(['name' => 'Suite Room', 'capacity' => 2, 'base_price' => 200000]);
        $room = Room::create(['room_number' => '401', 'room_type_id' => $roomType->id, 'floor' => 4, 'status' => 'Reserved', 'available_rooms' => 0]);
        $customer = User::factory()->create(['name' => 'Customer Three']);
        $reservation = Reservation::create([
            'booking_number' => 'HTL-2026-000002',
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::tomorrow(),
            'number_of_guests' => 1,
            'number_of_nights' => 1,
            'room_price' => 200000,
            'subtotal' => 200000,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => 200000,
            'status' => 'Confirmed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/reservations/'.$reservation->id.'/check-in');

        $response->assertStatus(422)->assertJsonPath('message', 'Pembayaran harus diselesaikan sebelum check-in.');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'Confirmed']);

        Payment::create([
            'reservation_id' => $reservation->id,
            'payment_number' => 'PAY-202610030002',
            'payment_method' => 'QRIS',
            'amount' => 200000,
            'paid_amount' => 200000,
            'change_amount' => 0,
            'payment_status' => 'Paid',
            'paid_at' => now(),
            'processed_by' => $user->id,
        ]);

        $checkIn = $this->postJson('/api/reservations/'.$reservation->id.'/check-in');

        $checkIn->assertOk()->assertJsonPath('room.status', 'Occupied');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'Checked In']);
    }
}
