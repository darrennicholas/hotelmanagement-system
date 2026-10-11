<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('service_charge_rate', 5, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
        });

        DB::table('hotel_settings')->insert([
            'tax_rate' => 0,
            'service_charge_rate' => 0,
            'currency' => 'IDR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('reservations', function (Blueprint $table): void {
            $table->decimal('service_charge_rate', 5, 2)->default(0)->after('tax_rate');
            $table->decimal('service_charge', 14, 2)->default(0)->after('tax');
            $table->timestamp('no_show_at')->nullable()->after('checked_out_at');
            $table->foreignId('no_show_by')->nullable()->after('no_show_at')->constrained('users')->nullOnDelete();
            $table->text('arrival_override_reason')->nullable()->after('no_show_by');
            $table->text('departure_override_reason')->nullable()->after('arrival_override_reason');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('transaction_type')->default('Payment')->after('payment_status')->index();
            $table->foreignId('reference_payment_id')->nullable()->after('transaction_type')->constrained('payments')->nullOnDelete();
            $table->text('notes')->nullable()->after('reference_payment_id');
            $table->timestamp('voided_at')->nullable()->after('notes');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('service_charge', 14, 2)->default(0)->after('tax');
            $table->decimal('paid_total', 14, 2)->default(0)->after('grand_total');
            $table->decimal('balance_due', 14, 2)->default(0)->after('paid_total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['service_charge', 'paid_total', 'balance_due']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['reference_payment_id']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['transaction_type', 'reference_payment_id', 'notes', 'voided_at', 'voided_by']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['no_show_by']);
            $table->dropColumn([
                'service_charge_rate',
                'service_charge',
                'no_show_at',
                'no_show_by',
                'arrival_override_reason',
                'departure_override_reason',
            ]);
        });

        Schema::dropIfExists('hotel_settings');
    }
};
