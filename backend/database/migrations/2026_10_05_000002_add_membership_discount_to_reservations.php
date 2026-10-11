<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('membership_id')->nullable()->after('user_id')->constrained('memberships')->nullOnDelete();
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax');
            $table->decimal('manual_discount', 14, 2)->default(0)->after('discount');
            $table->decimal('member_discount_rate', 5, 2)->default(0)->after('manual_discount');
            $table->unsignedInteger('member_min_guests')->default(0)->after('member_discount_rate');
            $table->decimal('member_discount_amount', 14, 2)->default(0)->after('member_discount_rate');
        });

        DB::statement('UPDATE reservations SET tax_rate = ROUND((tax / NULLIF(subtotal, 0)) * 100, 2) WHERE tax > 0 AND subtotal > 0');
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['membership_id']);
            $table->dropColumn([
                'membership_id',
                'tax_rate',
                'manual_discount',
                'member_discount_rate',
                'member_min_guests',
                'member_discount_amount',
            ]);
        });
    }
};
