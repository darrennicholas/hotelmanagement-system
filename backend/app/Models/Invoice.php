<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['reservation_id', 'invoice_number', 'subtotal', 'tax', 'service_charge', 'discount', 'grand_total', 'paid_total', 'balance_due', 'issued_at'];

    protected $casts = ['subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'service_charge' => 'decimal:2', 'discount' => 'decimal:2', 'grand_total' => 'decimal:2', 'paid_total' => 'decimal:2', 'balance_due' => 'decimal:2', 'issued_at' => 'datetime'];
}
