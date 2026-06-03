<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_log_in_with_their_username()
    {
        $user = User::factory()->create([
            'username' => 'jane',
            'email' => 'jane@example.test',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'jane',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_still_log_in_with_their_email()
    {
        $user = User::factory()->create([
            'username' => 'jane',
            'email' => 'jane@example.test',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'jane@example.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_fails_for_unknown_identifier()
    {
        User::factory()->create(['username' => 'jane']);

        $this->post(route('login.store'), [
            'email' => 'nobody',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password_when_using_username()
    {
        User::factory()->create(['username' => 'jane']);

        $this->post(route('login.store'), [
            'email' => 'jane',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
