<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_and_cancel_store_status_transition_audit_details(): void
    {
        $staffRole = Role::create(['name' => 'Receptionist']);
        $userRole = Role::create(['name' => 'User']);
        $staff = User::factory()->create(['role_id' => $staffRole->id]);
        $customer = User::factory()->create(['role_id' => $userRole->id]);
        $roomType = RoomType::create(['name' => 'Audit Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '901', 'room_type_id' => $roomType->id, 'status' => 'Reserved', 'available_rooms' => 0]);
        $reservation = Reservation::create([
            'booking_number' => 'HTL-AUDIT-1',
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(2),
            'number_of_guests' => 1,
            'number_of_nights' => 1,
            'room_price' => 100000,
            'subtotal' => 100000,
            'tax' => 0,
            'service_charge' => 0,
            'discount' => 0,
            'grand_total' => 100000,
            'status' => 'Pending',
            'created_by' => $customer->id,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/reservations/'.$reservation->id.'/confirm', ['reason' => 'Data booking diverifikasi.'])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'action' => 'reservation.confirmed',
            'entity_id' => $reservation->id,
            'old_status' => 'Pending',
            'new_status' => 'Confirmed',
            'reason' => 'Data booking diverifikasi.',
        ]);

        $this->postJson('/api/reservations/'.$reservation->id.'/cancel', ['reason' => 'Tamu membatalkan booking.'])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'action' => 'reservation.cancelled',
            'entity_id' => $reservation->id,
            'old_status' => 'Confirmed',
            'new_status' => 'Cancelled',
            'reason' => 'Tamu membatalkan booking.',
        ]);
    }
}
