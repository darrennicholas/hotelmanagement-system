<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 120);
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['full_name', 'phone']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('guest_id')->nullable()->after('user_id')->constrained('guests')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['guest_id']);
            $table->dropColumn('guest_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::dropIfExists('guests');
    }
};
