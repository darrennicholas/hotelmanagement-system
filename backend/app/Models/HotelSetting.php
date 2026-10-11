<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelSetting extends Model
{
    protected $fillable = ['tax_rate', 'service_charge_rate', 'currency'];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'service_charge_rate' => 'decimal:2',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'tax_rate' => 0,
            'service_charge_rate' => 0,
            'currency' => 'IDR',
        ]);
    }
}
