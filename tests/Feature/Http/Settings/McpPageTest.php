<?php

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/settings/mcp')->assertRedirect('/login');
    }

    public function test_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/mcp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/mcp')
                ->where('mcpUrl', fn ($url) => str_ends_with($url, '/mcp'))
                ->has('tools', 6));
    }
}
