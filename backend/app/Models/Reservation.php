<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['booking_number', 'user_id', 'room_id', 'check_in_date', 'check_out_date', 'number_of_guests', 'number_of_nights', 'room_price', 'subtotal', 'tax', 'discount', 'grand_total', 'status', 'notes', 'created_by', 'cancelled_by', 'cancelled_at', 'cancellation_reason', 'checked_in_at', 'checked_out_at'];
    protected $casts = ['check_in_date' => 'date', 'check_out_date' => 'date', 'cancelled_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'room_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'discount' => 'decimal:2', 'grand_total' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function room() { return $this->belongsTo(Room::class); }
    public function services() { return $this->hasMany(ReservationService::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function invoice() { return $this->hasOne(Invoice::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
