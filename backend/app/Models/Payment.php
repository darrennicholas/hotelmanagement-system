<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['reservation_id', 'payment_number', 'payment_method', 'amount', 'paid_amount', 'change_amount', 'payment_status', 'transaction_type', 'reference_payment_id', 'notes', 'paid_at', 'processed_by', 'voided_at', 'voided_by'];

    protected $casts = ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function referencePayment()
    {
        return $this->belongsTo(self::class, 'reference_payment_id');
    }

    public function refunds()
    {
        return $this->hasMany(self::class, 'reference_payment_id');
    }
}
