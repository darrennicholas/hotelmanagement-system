<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    public function index()
    {
        return RoomType::with('facilities')->latest()->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'bed_type' => 'nullable|string',
            'number_of_beds' => 'required|integer|min:1',
            'facility_ids' => 'sometimes|array',
            'facility_ids.*' => 'integer|exists:facilities,id',
        ]);

        $type = RoomType::create($data);
        if ($request->has('facility_ids')) $type->facilities()->sync($request->input('facility_ids'));

        return response()->json($type->load('facilities'), 201);
    }

    public function show(RoomType $roomType)
    {
        return $roomType->load('facilities', 'rooms');
    }

    public function update(Request $request, RoomType $roomType)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'bed_type' => 'nullable|string',
            'number_of_beds' => 'sometimes|integer|min:1',
            'status' => 'sometimes|boolean',
            'facility_ids' => 'sometimes|array',
            'facility_ids.*' => 'integer|exists:facilities,id',
        ]);

        $roomType->update($data);
        if ($request->has('facility_ids')) $roomType->facilities()->sync($request->input('facility_ids'));

        return $roomType->load('facilities');
    }

    public function destroy(RoomType $roomType)
    {
        abort_if($roomType->rooms()->exists(), 422, 'Tipe kamar masih digunakan.');
        $roomType->delete();

        return response()->noContent();
    }
}
