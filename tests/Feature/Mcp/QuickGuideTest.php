<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_serves_markdown_quick_guide(): void
    {
        $response = $this->get('/mcp');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $response->assertSee('Re:Mind MCP', false);
        $response->assertSee('list-projects', false);
        $response->assertSee('add-reminder', false);
        $response->assertSee('http://localhost:8000/mcp', false);
    }

    public function test_post_is_not_intercepted_by_markdown_route(): void
    {
        User::factory()->create();

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('Re:Mind MCP — Quick guide', (string) $response->getContent());
    }
}
