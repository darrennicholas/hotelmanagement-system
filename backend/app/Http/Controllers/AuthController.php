<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $role = Role::firstOrCreate(['name' => 'User']);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $role->id,
            'status' => true,
        ]);

        return response()->json([
            'message' => 'Akun berhasil dibuat. Silakan masuk.',
            'user' => $user->load('role'),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $user = User::with('role')->where('email', $data['email'])->first();
        abort_unless($user && $user->status && Hash::check($data['password'], $user->password), 422, 'Email atau password salah.');
        $token = $user->createToken('hotel-management')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function logout(Request $request) { $request->user()->currentAccessToken()?->delete(); return response()->json(['message' => 'Logout berhasil.']); }
    public function user(Request $request) { return $request->user()->load('role'); }
}
