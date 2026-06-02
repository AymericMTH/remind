<?php

namespace Tests\Feature\Models;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_a_cannot_view_user_bs_list(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $b->id]);

        $this->assertFalse($a->can('view', $list));
        $this->assertTrue($b->can('view', $list));
    }

    public function test_user_a_cannot_update_user_bs_reminder(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $b->id]);

        $this->assertFalse($a->can('update', $reminder));
        $this->assertTrue($b->can('update', $reminder));
    }

    public function test_user_a_cannot_delete_user_bs_reminder(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $b->id]);

        $this->assertFalse($a->can('delete', $reminder));
        $this->assertTrue($b->can('delete', $reminder));
    }

    public function test_owner_cannot_update_or_delete_inbox_list(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();

        $this->assertFalse($user->can('update', $inbox));
        $this->assertFalse($user->can('delete', $inbox));
    }

    public function test_owner_can_update_and_delete_non_inbox_list(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('update', $list));
        $this->assertTrue($user->can('delete', $list));
    }
}
