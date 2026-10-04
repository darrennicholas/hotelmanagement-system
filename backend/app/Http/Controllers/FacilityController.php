<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function index() { return Facility::with(['roomTypes:id,name'])->withCount('roomTypes')->orderBy('name')->get(); }
    public function store(Request $request) { return response()->json(Facility::create($request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string'])), 201); }
    public function update(Request $request, Facility $facility) { $facility->update($request->validate(['name' => 'sometimes|string|max:100', 'description' => 'nullable|string'])); return $facility; }
    public function destroy(Facility $facility) { $facility->delete(); return response()->noContent(); }
}
