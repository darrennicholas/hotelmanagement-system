<?php

namespace Tests\Feature;

use App\Models\HotelService;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HotelServiceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_use_services_without_catalog_status_or_quantity_limit(): void
    {
        $role = Role::create(['name' => 'User']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $roomType = RoomType::create(['name' => 'Unlimited Service Room', 'capacity' => 2, 'base_price' => 100]);
        $room = Room::create(['room_number' => '601', 'room_type_id' => $roomType->id, 'status' => 'Reserved', 'available_rooms' => 0]);
        $reservation = Reservation::create([
            'booking_number' => 'HTL-2026-000004',
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDay(),
            'number_of_guests' => 1,
            'number_of_nights' => 1,
            'room_price' => 100,
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => 100,
            'status' => 'Confirmed',
            'created_by' => $user->id,
        ]);
        $service = HotelService::create(['name' => 'Always Available Service', 'price' => 10, 'status' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/services')
            ->assertOk()
            ->assertJsonFragment(['id' => $service->id, 'name' => $service->name]);

        $payment = $this->actingAs($user, 'sanctum')->postJson('/api/reservations/'.$reservation->id.'/payments', [
            'payment_method' => 'QRIS',
            'paid_amount' => 100100,
            'services' => [[
                'hotel_service_id' => $service->id,
                'quantity' => 10000,
            ]],
        ]);

        $payment->assertCreated();
        $this->assertDatabaseHas('reservation_services', [
            'reservation_id' => $reservation->id,
            'hotel_service_id' => $service->id,
            'quantity' => 10000,
        ]);
    }
}
