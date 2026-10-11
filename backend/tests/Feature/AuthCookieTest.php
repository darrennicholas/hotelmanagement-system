<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthCookieTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_an_http_only_cookie_and_cookie_authenticates_requests(): void
    {
        $role = Role::create(['name' => 'User']);
        User::create([
            'name' => 'Cookie User',
            'email' => 'cookie@example.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => true,
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'cookie@example.test',
            'password' => 'password',
        ]);

        $login->assertOk();
        $login->assertJsonMissingPath('token');
        $this->assertNotNull($login->getCookie('hotel_token', false));
        $this->assertTrue($login->getCookie('hotel_token', false)->isHttpOnly());

        $token = $login->getCookie('hotel_token', false)->getValue();

        $this->withCredentials()
            ->withUnencryptedCookie('hotel_token', $token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'cookie@example.test');
    }
}
