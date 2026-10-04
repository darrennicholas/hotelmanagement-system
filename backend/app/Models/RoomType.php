<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $fillable = ['name', 'description', 'capacity', 'base_price', 'bed_type', 'number_of_beds', 'status'];
    protected $with = ['facilities'];
    protected $casts = ['base_price' => 'decimal:2', 'status' => 'boolean'];

    public function rooms() { return $this->hasMany(Room::class); }
    public function facilities() { return $this->belongsToMany(Facility::class, 'room_type_facility'); }
}
