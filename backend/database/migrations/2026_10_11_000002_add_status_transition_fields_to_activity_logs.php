<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->string('old_status')->nullable()->after('entity_id');
            $table->string('new_status')->nullable()->after('old_status');
            $table->text('reason')->nullable()->after('new_status');
            $table->index(['entity_type', 'entity_id', 'created_at'], 'activity_logs_entity_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex('activity_logs_entity_timeline_index');
            $table->dropColumn(['old_status', 'new_status', 'reason']);
        });
    }
};
