<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['role', 'membership'])->when($request->filled('role'), fn ($q) => $q->whereHas('role', fn ($role) => $role->where('name', $request->input('role'))));

        if ($request->user()?->hasRole(['Admin', 'Receptionist', 'Manager'])) {
            // Admin may see all users and may optionally filter by role.
        } else {
            $query->where('status', true);
            $query->whereHas('role', fn ($role) => $role->where('name', 'User'));
            $query->where('id', $request->user()->id);
        }

        return $query->latest()->paginate($request->integer('per_page', 15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create($data);

            if ($user->role()->where('name', 'User')->exists()) {
                Membership::ensureForUser($user);
            }

            return $user;
        });

        return response()->json($user->load(['role', 'membership']), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'sometimes|exists:roles,id',
            'status' => 'sometimes|boolean',
            'membership_active' => 'sometimes|boolean',
            'membership_discount_rate' => 'sometimes|numeric|min:0|max:100',
            'membership_min_guests' => 'sometimes|integer|min:2|max:100',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $membershipData = [
            'membership_discount_rate' => $data['membership_discount_rate'] ?? null,
            'membership_min_guests' => $data['membership_min_guests'] ?? null,
        ];
        unset($data['membership_active'], $data['membership_discount_rate'], $data['membership_min_guests']);
        $membershipActiveWasSent = $request->has('membership_active');

        DB::transaction(function () use ($data, $membershipData, $membershipActiveWasSent, $user): void {
            $user->update($data);
            $user->load('role');

            if ($user->role?->name !== 'User') {
                if (collect($membershipData)->filter(fn ($value) => $value !== null)->isNotEmpty() || $membershipActiveWasSent) {
                    abort(422, 'Hanya role User yang dapat memiliki member discount.');
                }

                $user->membership()?->update(['is_active' => false]);

                return;
            }

            $membership = Membership::ensureForUser($user);

            $membership->update([
                'discount_rate' => $membershipData['membership_discount_rate'] ?? $membership->discount_rate,
                'min_guests' => Membership::MINIMUM_GUESTS,
                'is_active' => true,
            ]);
        });

        return $user->fresh()->load(['role', 'membership']);
    }

    public function roles()
    {
        return Role::orderBy('name')->get();
    }
}
