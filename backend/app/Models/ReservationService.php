<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationService extends Model
{
    protected $fillable = ['reservation_id', 'hotel_service_id', 'quantity', 'price', 'subtotal'];
    protected $casts = ['price' => 'decimal:2', 'subtotal' => 'decimal:2'];
    public function service() { return $this->belongsTo(HotelService::class, 'hotel_service_id'); }
}
