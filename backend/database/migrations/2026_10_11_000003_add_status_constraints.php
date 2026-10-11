<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) return;

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('Pending', 'Confirmed', 'Checked In', 'Checked Out', 'Completed', 'Cancelled', 'No Show'))");
        DB::statement("ALTER TABLE rooms ADD CONSTRAINT rooms_status_check CHECK (status IN ('Available', 'Reserved', 'Occupied', 'Cleaning', 'Maintenance', 'Out of Order'))");
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) return;

        DB::statement('ALTER TABLE rooms DROP CONSTRAINT rooms_status_check');
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT reservations_status_check');
    }
};
