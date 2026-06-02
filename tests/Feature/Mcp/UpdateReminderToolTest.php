<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\UpdateReminder as Tool;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateReminderToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_title_and_notes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id, 'title' => 'Old']);

        $response = RemindServer::tool(Tool::class, [
            'reminder_id' => $r->id,
            'title' => 'New',
            'notes' => 'Update',
        ]);

        $response->assertOk();
        $this->assertSame('New', $r->fresh()->title);
        $this->assertSame('Update', $r->fresh()->notes);
    }

    public function test_unknown_reminder_returns_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = RemindServer::tool(Tool::class, ['reminder_id' => 9999, 'title' => 'X']);
        $response->assertSee('not found');
    }

    public function test_can_move_to_a_different_project(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        RemindServer::tool(Tool::class, ['reminder_id' => $r->id, 'project_id' => $list->id]);

        $this->assertSame($list->id, $r->fresh()->list_id);
    }
}
