<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sets_a_new_password_for_a_user_found_by_username()
    {
        $user = User::factory()->create(['username' => 'aymeric']);

        $this->artisan('user:password', ['identifier' => 'aymeric', '--password' => 'brand-new'])
            ->expectsOutputToContain('Updated password')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('brand-new', $user->refresh()->password));
    }

    public function test_command_sets_a_new_password_for_a_user_found_by_email()
    {
        $user = User::factory()->create(['email' => 'jane@example.test']);

        $this->artisan('user:password', ['identifier' => 'jane@example.test', '--password' => 'brand-new'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('brand-new', $user->refresh()->password));
    }

    public function test_command_clears_the_password_with_clear_flag()
    {
        $user = User::factory()->create(['username' => 'aymeric']);

        $this->artisan('user:password', ['identifier' => 'aymeric', '--clear' => true])
            ->expectsOutputToContain('Cleared password')
            ->assertSuccessful();

        $this->assertNull($user->refresh()->password);
    }

    public function test_command_fails_when_user_is_not_found()
    {
        $this->artisan('user:password', ['identifier' => 'ghost', '--password' => 'x'])
            ->expectsOutputToContain('No user found')
            ->assertFailed();
    }
}
