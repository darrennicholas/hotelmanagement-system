<?php

namespace App\Models;

use App\Enums\RoomStatus;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public const STATUSES = [
        RoomStatus::Available->value,
        RoomStatus::Reserved->value,
        RoomStatus::Occupied->value,
        RoomStatus::Cleaning->value,
        RoomStatus::Maintenance->value,
        RoomStatus::OutOfOrder->value,
    ];

    /** @return list<string> */
    public static function statusValues(): array
    {
        return RoomStatus::values();
    }

    protected $fillable = ['room_number', 'room_type_id', 'floor', 'status', 'available_rooms', 'description'];

    protected $casts = ['available_rooms' => 'integer'];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
