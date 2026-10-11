<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_staff_can_run_arrival_actions_and_no_show_releases_the_room(): void
    {
        $userRole = Role::create(['name' => 'User']);
        $receptionistRole = Role::create(['name' => 'Receptionist']);
        $customer = User::factory()->create(['role_id' => $userRole->id]);
        $staff = User::factory()->create(['role_id' => $receptionistRole->id]);
        $roomType = RoomType::create(['name' => 'Lifecycle Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '801', 'room_type_id' => $roomType->id, 'status' => 'Reserved', 'available_rooms' => 0]);
        $reservation = Reservation::create([
            'booking_number' => 'HTL-2026-000801',
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => now(),
            'check_out_date' => now()->addDay(),
            'number_of_guests' => 1,
            'number_of_nights' => 1,
            'room_price' => 100000,
            'subtotal' => 100000,
            'tax' => 0,
            'service_charge' => 0,
            'discount' => 0,
            'grand_total' => 100000,
            'status' => 'Confirmed',
            'created_by' => $customer->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/reservations/'.$reservation->id.'/check-in')
            ->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/reservations/'.$reservation->id.'/no-show', ['reason' => 'Tamu tidak datang.'])
            ->assertOk()
            ->assertJsonPath('status', 'No Show');

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'Available', 'available_rooms' => 1]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'reservation.no_show']);
    }
}
