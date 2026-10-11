<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_finance_settings_are_snapshotted_into_new_reservations(): void
    {
        $adminRole = Role::create(['name' => 'Admin']);
        $userRole = Role::create(['name' => 'User']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $customer = User::factory()->create(['role_id' => $userRole->id]);
        $roomType = RoomType::create(['name' => 'Taxed Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '700', 'room_type_id' => $roomType->id, 'status' => 'Available', 'available_rooms' => 1]);

        $this->actingAs($admin, 'sanctum')->patchJson('/api/settings', [
            'tax_rate' => 10,
            'service_charge_rate' => 5,
            'currency' => 'IDR',
        ])->assertOk();

        $this->actingAs($customer, 'sanctum')->postJson('/api/reservations', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'number_of_guests' => 1,
        ])->assertCreated()
            ->assertJsonPath('status', 'Pending')
            ->assertJsonPath('service_charge', '5000.00')
            ->assertJsonPath('tax', '10500.00')
            ->assertJsonPath('grand_total', '115500.00');
    }

    public function test_staff_can_record_partial_payments_and_checkout_only_after_settlement(): void
    {
        $receptionistRole = Role::create(['name' => 'Receptionist']);
        $staff = User::factory()->create(['role_id' => $receptionistRole->id]);
        $customer = User::factory()->create();
        $roomType = RoomType::create(['name' => 'Finance Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '701', 'room_type_id' => $roomType->id, 'status' => 'Occupied', 'available_rooms' => 0]);
        $reservation = Reservation::create([
            'booking_number' => 'HTL-2026-000701',
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => now()->subDay(),
            'check_out_date' => now(),
            'number_of_guests' => 1,
            'number_of_nights' => 1,
            'room_price' => 100000,
            'subtotal' => 100000,
            'tax' => 0,
            'service_charge' => 0,
            'discount' => 0,
            'grand_total' => 100000,
            'status' => 'Checked In',
            'checked_in_at' => now()->subDay(),
            'created_by' => $staff->id,
        ]);

        $firstPayment = $this->actingAs($staff, 'sanctum')->postJson('/api/reservations/'.$reservation->id.'/payments', [
            'payment_method' => 'Cash',
            'paid_amount' => 40000,
        ]);

        $firstPayment->assertCreated()->assertJsonPath('amount', '40000.00');
        $this->getJson('/api/reservations/'.$reservation->id.'/folio')
            ->assertOk()
            ->assertJsonPath('summary.balance', 60000);

        $this->postJson('/api/reservations/'.$reservation->id.'/check-out')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Sisa tagihan 60.000 harus diselesaikan sebelum check-out.');

        $this->postJson('/api/reservations/'.$reservation->id.'/payments', [
            'payment_method' => 'QRIS',
            'paid_amount' => 60000,
        ])->assertCreated();

        $this->postJson('/api/reservations/'.$reservation->id.'/check-out')
            ->assertOk()
            ->assertJsonPath('invoice.balance_due', '0.00');
    }

    public function test_manager_can_refund_and_admin_can_void_without_deleting_audit_history(): void
    {
        $managerRole = Role::create(['name' => 'Manager']);
        $adminRole = Role::create(['name' => 'Admin']);
        $manager = User::factory()->create(['role_id' => $managerRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $customer = User::factory()->create();
        $roomType = RoomType::create(['name' => 'Refund Room', 'capacity' => 2, 'base_price' => 100000]);
        $room = Room::create(['room_number' => '702', 'room_type_id' => $roomType->id, 'status' => 'Reserved', 'available_rooms' => 0]);
        $reservation = Reservation::create([
            'booking_number' => 'HTL-2026-000702',
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
            'status' => 'Confirmed',
            'created_by' => $manager->id,
        ]);

        $payment = $this->actingAs($manager, 'sanctum')->postJson('/api/reservations/'.$reservation->id.'/payments', [
            'payment_method' => 'QRIS',
            'paid_amount' => 100000,
        ])->assertCreated();

        $this->postJson('/api/payments/'.$payment->json('id').'/refund', [
            'amount' => 25000,
            'reason' => 'Koreksi layanan.',
        ])->assertCreated()->assertJsonPath('transaction_type', 'Refund');

        $secondPayment = $this->postJson('/api/reservations/'.$reservation->id.'/payments', [
            'payment_method' => 'Cash',
            'paid_amount' => 25000,
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson('/api/payments/'.$secondPayment->json('id').'/void', [
            'reason' => 'Salah input kasir.',
        ])->assertOk()->assertJsonPath('payment_status', 'Voided');

        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.refunded']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.voided']);

        $report = $this->actingAs($manager, 'sanctum')->getJson('/api/reports/revenue?from='.now()->toDateString().'&to='.now()->toDateString());
        $report->assertOk();
        $this->assertSame(75000.0, (float) $report->json('0.total'));
    }
}
