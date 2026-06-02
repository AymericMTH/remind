<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\CreateProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class CreateProjectToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_project_and_returns_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = RemindServer::tool(CreateProject::class, ['name' => 'Side hustle', 'color' => '#bf616a']);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $data) {
            $data->where('project.name', 'Side hustle')
                ->where('project.color', '#bf616a')
                ->etc();
        });
    }

    public function test_duplicate_name_returns_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        RemindServer::tool(CreateProject::class, ['name' => 'Work']);
        $response = RemindServer::tool(CreateProject::class, ['name' => 'work']);
        $response->assertSee('already exists');
    }
}
