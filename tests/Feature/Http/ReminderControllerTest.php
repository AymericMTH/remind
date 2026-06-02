<?php

namespace Tests\Feature\Http;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create(): void
    {
        $this->post('/reminders', ['title' => 'X', 'list_id' => 1])->assertRedirect('/login');
    }

    public function test_create_persists_reminder(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        $this->actingAs($user)
            ->post('/reminders', ['list_id' => $list->id, 'title' => 'A'])
            ->assertRedirect();
        $this->assertSame(1, $user->reminders()->count());
    }

    public function test_cannot_update_another_users_reminder(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $b->id, 'list_id' => $b->lists()->first()->id]);

        $this->actingAs($a)
            ->put("/reminders/{$r->id}", ['title' => 'hax'])
            ->assertForbidden();
    }

    public function test_complete_toggles_status(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $this->actingAs($user)->post("/reminders/{$r->id}/complete", ['done' => true])->assertRedirect();
        $this->assertSame('done', $r->fresh()->status);

        $this->actingAs($user)->post("/reminders/{$r->id}/complete", ['done' => false])->assertRedirect();
        $this->assertSame('open', $r->fresh()->status);
    }

    public function test_destroy_soft_deletes(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $this->actingAs($user)->delete("/reminders/{$r->id}")->assertRedirect();
        $this->assertNotNull(Reminder::withTrashed()->find($r->id)->deleted_at);
    }

    public function test_reorder_updates_positions(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        $a = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'position' => 1]);
        $b = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'position' => 2]);

        $this->actingAs($user)
            ->put('/reminders/reorder', ['list_id' => $list->id, 'order' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertLessThan($a->fresh()->position, $b->fresh()->position);
    }
}
