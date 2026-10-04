<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['reservation_id', 'invoice_number', 'subtotal', 'tax', 'discount', 'grand_total', 'issued_at'];
    protected $casts = ['subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'discount' => 'decimal:2', 'grand_total' => 'decimal:2', 'issued_at' => 'datetime'];
}
