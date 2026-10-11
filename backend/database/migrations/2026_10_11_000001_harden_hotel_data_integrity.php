<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('reservations', 'reservations_user_status_date_index')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->index(['user_id', 'status', 'check_in_date'], 'reservations_user_status_date_index');
            });
        }

        if (! Schema::hasIndex('reservations', 'reservations_guest_status_date_index')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->index(['guest_id', 'status', 'check_in_date'], 'reservations_guest_status_date_index');
            });
        }

        if (! Schema::hasIndex('payments', 'payments_settlement_index')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->index(['reservation_id', 'payment_status', 'transaction_type', 'paid_at'], 'payments_settlement_index');
            });
        }

        if (! Schema::hasIndex('reservation_services', 'reservation_services_lookup_index')) {
            Schema::table('reservation_services', function (Blueprint $table): void {
                $table->index(['reservation_id', 'hotel_service_id'], 'reservation_services_lookup_index');
            });
        }

        // SQLite does not support adding table-level CHECK constraints with
        // ALTER TABLE. Application validation covers it in tests; production
        // MySQL/MariaDB also gets database-level protection here.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_dates_check CHECK (check_out_date > check_in_date)');
            DB::statement('ALTER TABLE reservation_services ADD CONSTRAINT reservation_services_quantity_check CHECK (quantity > 0 AND price >= 0 AND subtotal >= 0)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amounts_check CHECK (amount >= 0 AND paid_amount >= 0 AND change_amount >= 0)');
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_amounts_check');
            DB::statement('ALTER TABLE reservation_services DROP CONSTRAINT reservation_services_quantity_check');
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT reservations_dates_check');
            // Customer XOR validation is enforced in ReservationController.
        }

        Schema::table('reservation_services', function (Blueprint $table): void {
            $table->dropIndex('reservation_services_lookup_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_settlement_index');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex('reservations_user_status_date_index');
            $table->dropIndex('reservations_guest_status_date_index');
        });
    }
};
