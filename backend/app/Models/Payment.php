<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['reservation_id', 'payment_number', 'payment_method', 'amount', 'paid_amount', 'change_amount', 'payment_status', 'paid_at', 'processed_by'];
    protected $casts = ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2', 'paid_at' => 'datetime'];
    public function reservation() { return $this->belongsTo(Reservation::class); }
}
