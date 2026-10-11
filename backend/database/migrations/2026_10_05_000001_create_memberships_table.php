<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name')->default('Member');
            $table->decimal('discount_rate', 5, 2)->default(10);
            $table->unsignedInteger('min_guests')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $userRoleId = DB::table('roles')->where('name', 'User')->value('id');

        if ($userRoleId) {
            $now = now();
            $userIds = DB::table('users')->where('role_id', $userRoleId)->pluck('id');

            foreach ($userIds as $userId) {
                DB::table('memberships')->insertOrIgnore([
                    'user_id' => $userId,
                    'name' => 'Member',
                    'discount_rate' => 10,
                    'min_guests' => 2,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
