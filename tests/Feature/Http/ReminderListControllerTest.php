<?php

namespace Tests\Feature\Http;

use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/lists', ['name' => 'X'])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_create_a_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/lists', ['name' => 'Work'])
            ->assertRedirect();
        $this->assertSame(2, $user->lists()->count()); // Inbox + Work
    }

    public function test_user_cannot_delete_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();

        $this->actingAs($user)
            ->delete("/lists/{$inbox->id}", ['strategy' => 'cascade'])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_list(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bsList = ReminderList::factory()->create(['user_id' => $b->id]);

        $this->actingAs($a)
            ->delete("/lists/{$bsList->id}", ['strategy' => 'cascade'])
            ->assertForbidden();
    }

    public function test_user_can_reorder_lists(): void
    {
        $user = User::factory()->create();
        $a = ReminderList::factory()->create(['user_id' => $user->id, 'position' => 1]);
        $b = ReminderList::factory()->create(['user_id' => $user->id, 'position' => 2]);

        $this->actingAs($user)
            ->put('/lists/reorder', ['order' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertLessThan($a->fresh()->position, $b->fresh()->position);
    }
}
