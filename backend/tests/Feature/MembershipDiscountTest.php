<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_automatically_receives_member_discount_for_two_guests(): void
    {
        $user = $this->createMemberUser();
        $roomType = RoomType::create([
            'name' => 'Deluxe Room',
            'capacity' => 2,
            'base_price' => 100000,
            'number_of_beds' => 1,
        ]);
        $room = Room::create([
            'room_number' => '301',
            'room_type_id' => $roomType->id,
            'status' => 'Available',
            'available_rooms' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/reservations', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_guests' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('discount', '20000.00')
            ->assertJsonPath('grand_total', '180000.00');

        $this->assertDatabaseHas('reservations', [
            'user_id' => $user->id,
            'membership_id' => $user->membership->id,
            'member_discount_rate' => 10,
            'member_discount_amount' => 20000,
            'discount' => 20000,
            'grand_total' => 180000,
        ]);
    }

    public function test_one_guest_can_book_without_member_discount(): void
    {
        $user = $this->createMemberUser();
        $roomType = RoomType::create([
            'name' => 'Suite Room',
            'capacity' => 4,
            'base_price' => 200000,
            'number_of_beds' => 2,
        ]);
        $room = Room::create([
            'room_number' => '401',
            'room_type_id' => $roomType->id,
            'status' => 'Available',
            'available_rooms' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/reservations', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'number_of_guests' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('discount', '0.00')
            ->assertJsonPath('member_discount_rate', '0.00');
    }

    public function test_existing_user_without_membership_is_repaired_automatically(): void
    {
        $role = Role::create(['name' => 'User']);
        $user = User::factory()->create(['role_id' => $role->id, 'status' => true]);
        $roomType = RoomType::create(['name' => 'Family Room', 'capacity' => 2, 'base_price' => 100000, 'number_of_beds' => 1]);
        $room = Room::create(['room_number' => '501', 'room_type_id' => $roomType->id, 'status' => 'Available', 'available_rooms' => 1]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/reservations', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'number_of_guests' => 2,
        ]);

        $response->assertCreated()->assertJsonPath('member_discount_rate', '10.00');
        $this->assertDatabaseHas('memberships', [
            'user_id' => $user->id,
            'is_active' => true,
            'min_guests' => 2,
        ]);
    }

    public function test_payment_uses_the_discounted_grand_total(): void
    {
        $user = $this->createMemberUser();
        $roomType = RoomType::create([
            'name' => 'Standard Room',
            'capacity' => 2,
            'base_price' => 100000,
            'number_of_beds' => 1,
        ]);
        $room = Room::create([
            'room_number' => '201',
            'room_type_id' => $roomType->id,
            'status' => 'Available',
            'available_rooms' => 1,
        ]);

        $reservationResponse = $this->actingAs($user, 'sanctum')->postJson('/api/reservations', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'number_of_guests' => 2,
        ])->assertCreated();

        $reservationId = $reservationResponse->json('id');

        $payment = $this->actingAs($user, 'sanctum')->postJson('/api/reservations/'.$reservationId.'/payments', [
            'payment_method' => 'QRIS',
            'paid_amount' => 90000,
        ]);

        $payment->assertCreated()->assertJsonPath('amount', '90000.00');
        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservationId,
            'amount' => 90000,
        ]);
    }

    private function createMemberUser(): User
    {
        $role = Role::create(['name' => 'User']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'status' => true,
        ]);

        Membership::create([
            'user_id' => $user->id,
            'name' => 'Member',
            'discount_rate' => 10,
            'min_guests' => 2,
            'is_active' => true,
        ]);

        return $user->load('membership');
    }
}
