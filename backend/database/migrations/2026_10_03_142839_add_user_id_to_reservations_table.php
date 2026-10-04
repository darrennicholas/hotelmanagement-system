<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        $hasGuestId = Schema::hasColumn('reservations', 'guest_id');
        $hasUserId = Schema::hasColumn('reservations', 'user_id');

        if (!$hasGuestId) {
            return;
        }

        if (!$hasUserId) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        $roleId = DB::table('roles')->where('name', 'User')->value('id');
        if (!$roleId) {
            $roleId = DB::table('roles')->insertGetId(['name' => 'User', 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (DB::table('guests')->get() as $guest) {
            $userId = $guest->email
                ? DB::table('users')->where('email', $guest->email)->value('id')
                : null;

            if (!$userId) {
                $email = $guest->email ?: "customer{$guest->id}@hotel.local";
                while (DB::table('users')->where('email', $email)->exists()) {
                    $email = "customer{$guest->id}-" . Str::lower(Str::random(6)) . '@hotel.local';
                }

                $userId = DB::table('users')->insertGetId([
                    'name' => $guest->full_name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'role_id' => $roleId,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('reservations')->where('guest_id', $guest->id)->whereNull('user_id')->update(['user_id' => $userId]);
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->dropColumn('guest_id');
        });

        Schema::dropIfExists('guests');
    }

    public function down(): void
    {
        // The base hotel schema now owns reservations.user_id; this migration only upgrades old databases.
    }
};
