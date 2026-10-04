<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    protected $fillable = ['name', 'description'];
    public function roomTypes() { return $this->belongsToMany(RoomType::class, 'room_type_facility'); }
}
