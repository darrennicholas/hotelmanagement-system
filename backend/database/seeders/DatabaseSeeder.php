<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\HotelService;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $receptionist = Role::firstOrCreate(['name' => 'Receptionist']);
        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $user = Role::firstOrCreate(['name' => 'User']);
        User::updateOrCreate(['email' => 'admin@hotel.test'], ['name' => 'Hotel Administrator', 'password' => Hash::make('password'), 'role_id' => $admin->id, 'status' => true]);
        User::updateOrCreate(['email' => 'receptionist@hotel.test'], ['name' => 'Front Desk', 'password' => Hash::make('password'), 'role_id' => $receptionist->id, 'status' => true]);
        User::updateOrCreate(['email' => 'manager@hotel.test'], ['name' => 'Hotel Manager', 'password' => Hash::make('password'), 'role_id' => $manager->id, 'status' => true]);
        $deluxe = RoomType::firstOrCreate(['name' => 'Deluxe Room'], ['description' => 'Kamar nyaman dengan fasilitas lengkap', 'capacity' => 2, 'base_price' => 1250000, 'bed_type' => 'King', 'number_of_beds' => 1]);
        $suite = RoomType::firstOrCreate(['name' => 'Suite Room'], ['description' => 'Suite luas untuk keluarga', 'capacity' => 4, 'base_price' => 2200000, 'bed_type' => 'King', 'number_of_beds' => 2]);
        foreach (['Wi-Fi', 'Air Conditioner', 'Television', 'Mini Bar', 'Breakfast'] as $name) {
            $facility = Facility::firstOrCreate(['name' => $name]);
            $deluxe->facilities()->syncWithoutDetaching($facility);
        }
        foreach (['Wi-Fi', 'Air Conditioner', 'Television', 'Breakfast'] as $name) {
            $facility = Facility::firstOrCreate(['name' => $name]);
            $suite->facilities()->syncWithoutDetaching($facility);
        }
        foreach (['301', '302', '303', '304', '305'] as $number) {
            Room::firstOrCreate(['room_number' => $number], ['room_type_id' => $deluxe->id, 'floor' => 3, 'status' => 'Available']);
        }
        foreach (['401', '402'] as $number) {
            Room::firstOrCreate(['room_number' => $number], ['room_type_id' => $suite->id, 'floor' => 4, 'status' => 'Available']);
        }
        foreach ([['Breakfast', 200000], ['Laundry', 150000], ['Airport Pickup', 300000], ['Room Service', 175000]] as [$name, $price]) {
            HotelService::firstOrCreate(['name' => $name], ['price' => $price, 'description' => 'Layanan hotel']);
        }
        $darren = User::updateOrCreate(['email' => 'darren@example.com'], ['name' => 'Darren Suwito', 'password' => Hash::make('password'), 'role_id' => $user->id, 'status' => true]);
        Membership::ensureForUser($darren);
    }
}
