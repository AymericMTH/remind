<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\AddReminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddReminderToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_by_project_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $work = ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);

        $response = RemindServer::tool(AddReminder::class, [
            'project_id' => $work->id,
            'title' => 'Refactor MCP tool',
            'context' => ['repo' => 'git@github.com:foo/bar.git', 'file' => 'app/X.php'],
        ]);

        $response->assertOk();
        $r = $user->reminders()->first();
        $this->assertSame('Refactor MCP tool', $r->title);
        $this->assertSame($work->id, $r->list_id);
        $this->assertSame('foo/bar', $r->context['repo_label']);
    }

    public function test_adds_by_project_name_case_insensitive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);

        $response = RemindServer::tool(AddReminder::class, [
            'project_name' => 'work',
            'title' => 'A',
        ]);

        $response->assertOk();
        $this->assertSame(1, $user->reminders()->count());
    }

    public function test_unknown_project_name_returns_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = RemindServer::tool(AddReminder::class, [
            'project_name' => 'no-such-list',
            'title' => 'A',
        ]);
        $response->assertSee('No project named');
    }

    public function test_either_project_id_or_project_name_is_required(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = RemindServer::tool(AddReminder::class, ['title' => 'A']);
        $response->assertSee('project');
    }
}
