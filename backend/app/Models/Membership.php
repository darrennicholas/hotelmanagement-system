<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    public const DEFAULT_DISCOUNT_RATE = 10;

    public const MINIMUM_GUESTS = 2;

    protected $fillable = [
        'user_id',
        'name',
        'discount_rate',
        'min_guests',
        'is_active',
    ];

    protected $casts = [
        'discount_rate' => 'decimal:2',
        'min_guests' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Every account with the User role receives an active membership.
     * Existing rows are repaired as well so old data follows the same rule.
     */
    public static function ensureForUser(User $user): self
    {
        $membership = $user->membership()->firstOrCreate([], [
            'name' => 'Member',
            'discount_rate' => self::DEFAULT_DISCOUNT_RATE,
            'min_guests' => self::MINIMUM_GUESTS,
            'is_active' => true,
        ]);

        $changes = [];

        if (! $membership->is_active) {
            $changes['is_active'] = true;
        }

        if ((int) $membership->min_guests !== self::MINIMUM_GUESTS) {
            $changes['min_guests'] = self::MINIMUM_GUESTS;
        }

        if ((float) $membership->discount_rate <= 0) {
            $changes['discount_rate'] = self::DEFAULT_DISCOUNT_RATE;
        }

        if ($changes) {
            $membership->update($changes);
            $membership->refresh();
        }

        return $membership;
    }
}
