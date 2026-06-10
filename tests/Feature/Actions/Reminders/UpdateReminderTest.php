<?php

namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\UpdateReminder;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_title_notes_and_due_date(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $updated = (new UpdateReminder)->run($reminder, [
            'title' => 'Edited',
            'notes' => 'New notes',
            'soft_due_date' => '2026-07-04',
        ]);

        $this->assertSame('Edited', $updated->title);
        $this->assertSame('New notes', $updated->notes);
        $this->assertSame('2026-07-04', $updated->soft_due_date->toDateString());
    }

    public function test_can_move_to_another_list_owned_by_same_user(): void
    {
        $user = User::factory()->create();
        $a = $user->lists()->first();
        $b = ReminderList::factory()->create(['user_id' => $user->id]);
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $a->id]);

        (new UpdateReminder)->run($reminder, ['list_id' => $b->id]);

        $this->assertSame($b->id, $reminder->fresh()->list_id);
    }

    public function test_moving_to_another_list_appends_tail_position(): void
    {
        $user = User::factory()->create();
        $source = $user->lists()->first();
        $target = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $target->id,
            'position' => 4,
        ]);
        Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $target->id,
            'position' => 9,
        ]);
        $moving = Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $source->id,
            'position' => 2,
        ]);

        (new UpdateReminder)->run($moving, ['list_id' => $target->id]);

        $this->assertSame(10, $moving->fresh()->position);
    }

    public function test_no_op_when_list_id_matches_current(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        $reminder = Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $list->id,
            'position' => 7,
        ]);

        (new UpdateReminder)->run($reminder, ['list_id' => $list->id]);

        $this->assertSame(7, $reminder->fresh()->position);
    }

    public function test_rejects_move_to_list_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);
        $otherList = ReminderList::factory()->create(['user_id' => $other->id]);

        $this->expectException(ValidationException::class);
        (new UpdateReminder)->run($reminder, ['list_id' => $otherList->id]);
    }

    public function test_normalizes_context_on_update(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        (new UpdateReminder)->run($reminder, [
            'context' => ['repo' => 'git@github.com:foo/bar.git'],
        ]);

        $this->assertSame('foo/bar', $reminder->fresh()->context['repo_label']);
    }
}
