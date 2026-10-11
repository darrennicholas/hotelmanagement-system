<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['booking_number', 'user_id', 'guest_id', 'membership_id', 'room_id', 'check_in_date', 'check_out_date', 'number_of_guests', 'number_of_nights', 'room_price', 'subtotal', 'tax', 'tax_rate', 'service_charge_rate', 'service_charge', 'discount', 'manual_discount', 'member_discount_rate', 'member_min_guests', 'member_discount_amount', 'grand_total', 'status', 'notes', 'created_by', 'cancelled_by', 'cancelled_at', 'cancellation_reason', 'checked_in_at', 'checked_out_at', 'no_show_at', 'no_show_by', 'arrival_override_reason', 'departure_override_reason'];

    protected $casts = ['check_in_date' => 'date', 'check_out_date' => 'date', 'cancelled_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'no_show_at' => 'datetime', 'room_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'tax_rate' => 'decimal:2', 'service_charge_rate' => 'decimal:2', 'service_charge' => 'decimal:2', 'discount' => 'decimal:2', 'manual_discount' => 'decimal:2', 'member_discount_rate' => 'decimal:2', 'member_min_guests' => 'integer', 'member_discount_amount' => 'decimal:2', 'grand_total' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function services()
    {
        return $this->hasMany(ReservationService::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
