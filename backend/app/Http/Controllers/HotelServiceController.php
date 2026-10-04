<?php

namespace App\Http\Controllers;

use App\Models\HotelService;
use Illuminate\Http\Request;

class HotelServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = HotelService::query()->orderBy('name');
        if (!($request->boolean('all') && $request->user()?->hasRole('Admin'))) {
            $query->where('status', true);
        }
        return $query->get();
    }
    public function store(Request $request) { return response()->json(HotelService::create($request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string', 'price' => 'required|numeric|min:0'])), 201); }
    public function update(Request $request, HotelService $service) { $service->update($request->validate(['name' => 'sometimes|string|max:100', 'description' => 'nullable|string', 'price' => 'sometimes|numeric|min:0', 'status' => 'sometimes|boolean'])); return $service; }
}
