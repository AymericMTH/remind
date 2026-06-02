<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\AddReminder;
use App\Mcp\Tools\CompleteReminder;
use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListReminders;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class EndToEndCaptureFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_capture_flow(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user); // observer auto-created Inbox

        // 1. Inspect what projects exist.
        $r1 = RemindServer::tool(ListProjects::class, []);
        $r1->assertOk();
        $r1->assertSee('Inbox');

        // 2. Create a new project for this work.
        $r2 = RemindServer::tool(CreateProject::class, ['name' => 'Re:Mind dev', 'color' => '#7aa2f7']);
        $r2->assertOk();
        $projectId = ReminderList::where('name', 'Re:Mind dev')->where('user_id', $user->id)->value('id');
        $this->assertNotNull($projectId);

        // 3. File a reminder with context.
        $r3 = RemindServer::tool(AddReminder::class, [
            'project_id' => $projectId,
            'title' => 'Refactor MCP route binding',
            'context' => [
                'repo' => 'git@github.com:foo/bar.git',
                'branch' => 'feature/mcp',
                'file' => 'routes/ai.php',
                'cwd' => '/home/aymeric/zWEB/remind',
            ],
        ]);
        $r3->assertOk();
        $reminder = Reminder::where('user_id', $user->id)->where('title', 'Refactor MCP route binding')->first();
        $this->assertNotNull($reminder);
        $this->assertSame($projectId, $reminder->list_id);
        $this->assertSame('foo/bar', $reminder->context['repo_label']);

        // 4. List reminders for the new project — should see our one.
        $r4 = RemindServer::tool(ListReminders::class, ['project_id' => $projectId]);
        $r4->assertOk();
        $r4->assertStructuredContent(fn (AssertableJson $json) => $json->has('reminders', 1)->etc());

        // 5. Complete it.
        $r5 = RemindServer::tool(CompleteReminder::class, ['reminder_id' => $reminder->id]);
        $r5->assertOk();
        $this->assertSame('done', $reminder->fresh()->status);

        // 6. Default list (open only) should now be empty for this project.
        $r6 = RemindServer::tool(ListReminders::class, ['project_id' => $projectId]);
        $r6->assertStructuredContent(fn (AssertableJson $json) => $json->has('reminders', 0)->etc());

        // 7. status=all surfaces the completed one again.
        $r7 = RemindServer::tool(ListReminders::class, ['project_id' => $projectId, 'status' => 'all']);
        $r7->assertStructuredContent(fn (AssertableJson $json) => $json->has('reminders', 1)->etc());
    }
}
