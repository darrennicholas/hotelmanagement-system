<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) { $table->id(); $table->string('name')->unique(); $table->timestamps(); });
        Schema::table('users', function (Blueprint $table) { $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete(); $table->boolean('status')->default(true)->after('password'); });
        Schema::create('room_types', function (Blueprint $table) { $table->id(); $table->string('name'); $table->text('description')->nullable(); $table->unsignedInteger('capacity')->default(1); $table->decimal('base_price', 14, 2); $table->string('bed_type')->nullable(); $table->unsignedInteger('number_of_beds')->default(1); $table->boolean('status')->default(true); $table->timestamps(); });
        Schema::create('facilities', function (Blueprint $table) { $table->id(); $table->string('name'); $table->text('description')->nullable(); $table->timestamps(); });
        Schema::create('room_type_facility', function (Blueprint $table) { $table->id(); $table->foreignId('room_type_id')->constrained()->cascadeOnDelete(); $table->foreignId('facility_id')->constrained()->cascadeOnDelete(); $table->unique(['room_type_id', 'facility_id']); });
        Schema::create('rooms', function (Blueprint $table) { $table->id(); $table->string('room_number')->unique(); $table->foreignId('room_type_id')->constrained()->restrictOnDelete(); $table->unsignedInteger('floor')->nullable(); $table->string('status')->default('Available')->index(); $table->text('description')->nullable(); $table->timestamps(); });
        Schema::create('reservations', function (Blueprint $table) { $table->id(); $table->string('booking_number')->unique(); $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); $table->foreignId('room_id')->constrained()->restrictOnDelete(); $table->date('check_in_date')->index(); $table->date('check_out_date')->index(); $table->unsignedInteger('number_of_guests')->default(1); $table->unsignedInteger('number_of_nights'); $table->decimal('room_price', 14, 2); $table->decimal('subtotal', 14, 2)->default(0); $table->decimal('tax', 14, 2)->default(0); $table->decimal('discount', 14, 2)->default(0); $table->decimal('grand_total', 14, 2)->default(0); $table->string('status')->default('Pending')->index(); $table->text('notes')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('cancelled_at')->nullable(); $table->string('cancellation_reason')->nullable(); $table->timestamp('checked_in_at')->nullable(); $table->timestamp('checked_out_at')->nullable(); $table->timestamps(); $table->index(['room_id', 'check_in_date', 'check_out_date']); });
        Schema::create('hotel_services', function (Blueprint $table) { $table->id(); $table->string('name'); $table->text('description')->nullable(); $table->decimal('price', 14, 2); $table->boolean('status')->default(true); $table->timestamps(); });
        Schema::create('reservation_services', function (Blueprint $table) { $table->id(); $table->foreignId('reservation_id')->constrained()->cascadeOnDelete(); $table->foreignId('hotel_service_id')->constrained()->restrictOnDelete(); $table->unsignedInteger('quantity')->default(1); $table->decimal('price', 14, 2); $table->decimal('subtotal', 14, 2); $table->timestamps(); });
        Schema::create('payments', function (Blueprint $table) { $table->id(); $table->foreignId('reservation_id')->constrained()->restrictOnDelete(); $table->string('payment_number')->unique(); $table->string('payment_method'); $table->decimal('amount', 14, 2); $table->decimal('paid_amount', 14, 2); $table->decimal('change_amount', 14, 2)->default(0); $table->string('payment_status')->default('Paid'); $table->timestamp('paid_at')->nullable(); $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); });
        Schema::create('invoices', function (Blueprint $table) { $table->id(); $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete(); $table->string('invoice_number')->unique(); $table->decimal('subtotal', 14, 2); $table->decimal('tax', 14, 2); $table->decimal('discount', 14, 2); $table->decimal('grand_total', 14, 2); $table->timestamp('issued_at'); $table->timestamps(); });
        Schema::create('activity_logs', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->string('entity_type')->nullable(); $table->unsignedBigInteger('entity_id')->nullable(); $table->text('description')->nullable(); $table->timestamp('created_at')->useCurrent(); $table->index(['entity_type', 'entity_id']); });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs'); Schema::dropIfExists('invoices'); Schema::dropIfExists('payments'); Schema::dropIfExists('reservation_services'); Schema::dropIfExists('hotel_services'); Schema::dropIfExists('reservations'); Schema::dropIfExists('rooms'); Schema::dropIfExists('room_type_facility'); Schema::dropIfExists('facilities'); Schema::dropIfExists('room_types'); Schema::table('users', function (Blueprint $table) { $table->dropConstrainedForeignId('role_id'); $table->dropColumn('status'); }); Schema::dropIfExists('roles');
    }
};
