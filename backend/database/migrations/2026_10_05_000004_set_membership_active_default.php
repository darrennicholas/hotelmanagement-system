<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('memberships')) {
            return;
        }

        Schema::table('memberships', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->change();
        });
    }

    public function down(): void
    {
        // Memberships for role User remain active by design.
    }
};
