<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role')->when($request->filled('role'), fn ($q) => $q->whereHas('role', fn ($role) => $role->where('name', $request->input('role'))));

        if ($request->user()?->hasRole('Admin')) {
            // Admin may see all users and may optionally filter by role.
        } else {
            $query->where('status', true);
            $query->whereHas('role', fn ($role) => $role->where('name', 'User'));
            $query->where('id', $request->user()->id);
        }

        return $query->latest()->paginate($request->integer('per_page', 15));
    }
    public function store(Request $request) { $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8', 'role_id' => 'required|exists:roles,id']); $user = User::create($data); return response()->json($user->load('role'), 201); }
    public function update(Request $request, User $user) { $data = $request->validate(['name' => 'sometimes|string|max:100', 'email' => 'sometimes|email|unique:users,email,'.$user->id, 'password' => 'nullable|string|min:8', 'role_id' => 'sometimes|exists:roles,id', 'status' => 'sometimes|boolean']); if (empty($data['password'])) unset($data['password']); $user->update($data); return $user->load('role'); }
    public function roles() { return Role::orderBy('name')->get(); }
}
