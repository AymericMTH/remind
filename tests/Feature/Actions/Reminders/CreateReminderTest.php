<?php

namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\CreateReminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_reminder_with_normalized_context(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first(); // Inbox

        $reminder = (new CreateReminder)->run($user, [
            'list_id' => $list->id,
            'title' => 'Refactor MCP tool',
            'notes' => 'Use Action classes.',
            'context' => ['repo' => 'git@github.com:foo/bar.git', 'file' => 'app/X.php'],
        ]);

        $this->assertSame('Refactor MCP tool', $reminder->title);
        $this->assertSame('open', $reminder->status);
        $this->assertSame('foo/bar', $reminder->context['repo_label']);
    }

    public function test_assigns_next_position_within_list(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        $a = (new CreateReminder)->run($user, ['list_id' => $list->id, 'title' => 'A']);
        $b = (new CreateReminder)->run($user, ['list_id' => $list->id, 'title' => 'B']);
        $this->assertGreaterThan($a->position, $b->position);
    }

    public function test_rejects_list_owned_by_another_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bsList = ReminderList::factory()->create(['user_id' => $b->id]);

        $this->expectException(ValidationException::class);
        (new CreateReminder)->run($a, ['list_id' => $bsList->id, 'title' => 'X']);
    }
}
