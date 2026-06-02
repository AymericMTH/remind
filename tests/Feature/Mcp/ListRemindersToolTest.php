<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\ListReminders;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ListRemindersToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_open_reminders_by_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->count(2)->create(['user_id' => $user->id, 'list_id' => $list->id]);
        Reminder::factory()->done()->create(['user_id' => $user->id, 'list_id' => $list->id]);

        $response = RemindServer::tool(ListReminders::class, []);
        $response->assertOk();
        $response->assertStructuredContent(fn (AssertableJson $json) => $json->has('reminders', 2)->etc());
    }

    public function test_filters_by_project_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $a = ReminderList::factory()->create(['user_id' => $user->id]);
        $b = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $a->id]);
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $b->id]);

        $response = RemindServer::tool(ListReminders::class, ['project_id' => $a->id]);
        $response->assertStructuredContent(fn (AssertableJson $json) => $json->has('reminders', 1)->etc());
    }

    public function test_filters_by_query_against_title_and_notes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $list = $user->lists()->first();
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'title' => 'Refactor MCP tool']);
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'title' => 'Walk the dog']);

        $response = RemindServer::tool(ListReminders::class, ['query' => 'mcp']);
        $response->assertStructuredContent(fn (AssertableJson $json) => $json->has('reminders', 1)->etc());
    }
}
