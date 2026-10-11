<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationCheckOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_moves_room_to_cleaning_until_housekeeping_releases_it(): void
    {
        $staffRole = Role::create(['name' => 'Receptionist']);
        $customerRole = Role::create(['name' => 'User']);
        $user = User::factory()->create(['role_id' => $staffRole->id]);
        $roomType = RoomType::create(['name' => 'Deluxe Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '301', 'room_type_id' => $roomType->id, 'floor' => 3, 'status' => 'Occupied', 'available_rooms' => 0]);
        $customer = User::factory()->create(['name' => 'Customer One', 'role_id' => $customerRole->id]);
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

        $checkout = $this->actingAs($user, 'sanctum')->postJson('/api/reservations/'.$reservation->id.'/check-out', [
            'override_reason' => 'Tamu pulang lebih awal.',
        ]);

        $checkout->assertOk()->assertJsonPath('room.status', 'Cleaning');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'Cleaning', 'available_rooms' => 0]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'Checked Out']);

        $room->update(['status' => 'Available', 'available_rooms' => 1]);
        $newCustomer = User::factory()->create(['name' => 'Customer Two', 'role_id' => $customerRole->id]);
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

    public function test_staff_can_check_in_a_confirmed_reservation_before_full_payment(): void
    {
        $staffRole = Role::create(['name' => 'Receptionist']);
        $user = User::factory()->create(['role_id' => $staffRole->id]);
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

        $response->assertOk()->assertJsonPath('room.status', 'Occupied');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'Checked In']);
    }

    public function test_paid_reservation_cannot_be_cancelled(): void
    {
        $userRole = Role::create(['name' => 'User']);
        $user = User::factory()->create(['role_id' => $userRole->id]);
        $roomType = RoomType::create(['name' => 'Paid Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '501', 'room_type_id' => $roomType->id, 'floor' => 5, 'status' => 'Reserved', 'available_rooms' => 0]);
        $customer = $user;
        $reservation = Reservation::create([
            'booking_number' => 'HTL-2026-000003',
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDay(),
            'number_of_guests' => 1,
            'number_of_nights' => 1,
            'room_price' => 100000,
            'subtotal' => 100000,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => 100000,
            'status' => 'Confirmed',
            'created_by' => $user->id,
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'payment_number' => 'PAY-202610030003',
            'payment_method' => 'QRIS',
            'amount' => 100000,
            'paid_amount' => 100000,
            'change_amount' => 0,
            'payment_status' => 'Paid',
            'paid_at' => now(),
            'processed_by' => $user->id,
        ]);

        $cancel = $this->actingAs($user, 'sanctum')->postJson('/api/reservations/'.$reservation->id.'/cancel', [
            'reason' => 'Perjalanan dibatalkan.',
        ]);

        $cancel->assertStatus(422)->assertJsonPath('message', 'Reservasi berbayar harus dibatalkan oleh staff agar proses refund tercatat.');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'Confirmed']);
    }
}
