<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'description', 'old_status', 'new_status', 'reason', 'created_at'];

    public static function recordStatusChange(
        ?int $userId,
        string $action,
        string $entityType,
        int $entityId,
        ?string $oldStatus,
        string $newStatus,
        ?string $reason = null,
    ): self {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'description' => trim(collect([
                $oldStatus ? "{$oldStatus} -> {$newStatus}" : "-> {$newStatus}",
                $reason ? "Alasan: {$reason}" : null,
            ])->filter()->implode(' | ')),
            'created_at' => now(),
        ]);
    }
}
