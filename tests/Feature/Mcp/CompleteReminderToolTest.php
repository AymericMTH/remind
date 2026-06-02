<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\CompleteReminder as Tool;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteReminderToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_reminder_done(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $response = RemindServer::tool(Tool::class, ['reminder_id' => $r->id]);
        $response->assertOk();
        $this->assertSame('done', $r->fresh()->status);
    }

    public function test_unknown_reminder_returns_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = RemindServer::tool(Tool::class, ['reminder_id' => 9999]);
        $response->assertSee('not found');
    }
}
