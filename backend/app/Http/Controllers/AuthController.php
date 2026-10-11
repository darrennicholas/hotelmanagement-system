<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $user = DB::transaction(function () use ($data): User {
            $role = Role::firstOrCreate(['name' => 'User']);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role_id' => $role->id,
                'status' => true,
            ]);
            Membership::ensureForUser($user);

            return $user;
        });

        return response()->json([
            'message' => 'Akun berhasil dibuat. Silakan masuk.',
            'user' => $user->load(['role', 'membership']),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $user = User::with(['role', 'membership'])->where('email', $data['email'])->first();
        abort_unless($user && $user->status && Hash::check($data['password'], $user->password), 422, 'Email atau password salah.');
        if ($user->hasRole('User')) {
            Membership::ensureForUser($user);
            $user->load('membership');
        }
        $token = $user->createToken('hotel-management', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json(['user' => $user])
            ->withCookie(cookie(
                'hotel_token',
                $token,
                60 * 24 * 7,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'strict'
            ));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil.'])
            ->withCookie(cookie()->forget('hotel_token'));
    }

    public function user(Request $request)
    {
        if ($request->user()->hasRole('User')) {
            Membership::ensureForUser($request->user());
        }

        return $request->user()->load(['role', 'membership']);
    }
}
