<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['room_number', 'room_type_id', 'floor', 'status', 'available_rooms', 'description'];

    protected $casts = ['available_rooms' => 'integer'];
    public function roomType() { return $this->belongsTo(RoomType::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
}
