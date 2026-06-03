<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_a_password_can_log_in_by_submitting_an_empty_password()
    {
        $user = User::factory()->passwordless()->create([
            'username' => 'aymeric',
            'email' => 'aymeric@example.test',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'aymeric',
            'password' => '',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_user_without_a_password_can_also_log_in_with_their_email_and_empty_password()
    {
        $user = User::factory()->passwordless()->create([
            'email' => 'aymeric@example.test',
        ]);

        $this->post(route('login.store'), [
            'email' => 'aymeric@example.test',
            'password' => '',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_without_a_password_cannot_log_in_with_a_non_empty_password()
    {
        User::factory()->passwordless()->create([
            'username' => 'aymeric',
        ]);

        $this->post(route('login.store'), [
            'email' => 'aymeric',
            'password' => 'anything',
        ]);

        $this->assertGuest();
    }

    public function test_user_with_a_password_cannot_log_in_with_an_empty_password()
    {
        User::factory()->create([
            'username' => 'jane',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => 'jane',
            'password' => '',
        ]);

        $this->assertGuest();
    }
}
