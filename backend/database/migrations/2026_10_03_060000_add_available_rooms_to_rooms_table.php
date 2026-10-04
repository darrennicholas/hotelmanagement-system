<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('available_rooms')->default(1)->after('status');
        });

        DB::table('rooms')
            ->whereIn('status', ['Reserved', 'Occupied', 'Cleaning', 'Maintenance'])
            ->update(['available_rooms' => 0]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('available_rooms');
        });
    }
};
