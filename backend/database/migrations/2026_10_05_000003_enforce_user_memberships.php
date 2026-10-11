<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('memberships') || ! Schema::hasTable('roles')) {
            return;
        }

        $userRoleId = DB::table('roles')->where('name', 'User')->value('id');
        if (! $userRoleId) {
            return;
        }

        $now = now();
        $userIds = DB::table('users')->where('role_id', $userRoleId)->pluck('id');

        foreach ($userIds as $userId) {
            $membership = DB::table('memberships')->where('user_id', $userId)->first();

            if (! $membership) {
                DB::table('memberships')->insert([
                    'user_id' => $userId,
                    'name' => 'Member',
                    'discount_rate' => 10,
                    'min_guests' => 2,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('memberships')->where('id', $membership->id)->update([
                'is_active' => true,
                'discount_rate' => (float) $membership->discount_rate > 0 ? $membership->discount_rate : 10,
                'min_guests' => 2,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Memberships are part of the user account contract and should not be
        // disabled again when rolling back unrelated application changes.
    }
};
