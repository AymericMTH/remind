<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleUserMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_zero_users_exist(): void
    {
        $response = $this->postJson('/mcp', $this->mcpHandshake());
        $response->assertStatus(500);
    }

    public function test_throws_when_multiple_users_exist(): void
    {
        User::factory()->count(2)->create();
        $response = $this->postJson('/mcp', $this->mcpHandshake());
        $response->assertStatus(500);
    }

    public function test_succeeds_with_exactly_one_user(): void
    {
        User::factory()->create();
        $response = $this->postJson('/mcp', $this->mcpHandshake());
        $response->assertOk();
    }

    /** @return array{jsonrpc:string,id:int,method:string,params:array} */
    private function mcpHandshake(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass()],
        ];
    }
}
