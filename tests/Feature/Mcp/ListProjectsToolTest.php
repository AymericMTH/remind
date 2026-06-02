<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\ListProjects;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListProjectsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_lists_with_open_counts(): void
    {
        $user = User::factory()->create();
        $work = ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);
        Reminder::factory()->count(2)->create(['user_id' => $user->id, 'list_id' => $work->id]);
        Reminder::factory()->done()->create(['user_id' => $user->id, 'list_id' => $work->id]);

        $response = RemindServer::actingAs($user)->tool(ListProjects::class, []);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->has('projects')
                ->where(
                    'projects',
                    fn ($projects) => collect($projects)->contains(
                        fn ($p) => $p['name'] === 'Work' && $p['open_count'] === 2
                    )
                )
                ->etc();
        });
    }
}
