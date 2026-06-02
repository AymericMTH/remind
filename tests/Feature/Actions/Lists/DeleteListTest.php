<?php

namespace Tests\Feature\Actions\Lists;

use App\Actions\Lists\DeleteList;
use App\Actions\Lists\DeleteStrategy;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteListTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_to_inbox_moves_children_then_deletes_list(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->count(3)->create(['user_id' => $user->id, 'list_id' => $list->id]);

        (new DeleteList())->run($list, DeleteStrategy::MoveToInbox);

        $this->assertNull(ReminderList::find($list->id));
        $inbox = $user->lists()->where('is_inbox', true)->first();
        $this->assertSame(3, $inbox->reminders()->count());
    }

    public function test_cascade_hard_deletes_children_and_list(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->count(2)->create(['user_id' => $user->id, 'list_id' => $list->id]);

        (new DeleteList())->run($list, DeleteStrategy::Cascade);

        $this->assertNull(ReminderList::find($list->id));
        $this->assertSame(0, Reminder::withTrashed()->where('list_id', $list->id)->count());
    }

    public function test_inbox_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();
        $this->expectException(\DomainException::class);
        (new DeleteList())->run($inbox, DeleteStrategy::Cascade);
    }

    public function test_move_to_inbox_also_re_parents_soft_deleted_children(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->count(2)->create(['user_id' => $user->id, 'list_id' => $list->id]);
        $trashed = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id]);
        $trashed->delete();

        (new DeleteList())->run($list, DeleteStrategy::MoveToInbox);

        $this->assertNull(ReminderList::find($list->id));
        $inbox = $user->lists()->where('is_inbox', true)->first();
        $this->assertSame(2, $inbox->reminders()->count());
        $this->assertSame(3, $inbox->reminders()->withTrashed()->count());
    }
}
