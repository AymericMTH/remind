<?php

namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\CompleteReminder;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_sets_status_and_completed_at(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        (new CompleteReminder())->run($r, true);

        $this->assertSame('done', $r->fresh()->status);
        $this->assertNotNull($r->fresh()->completed_at);
    }

    public function test_uncomplete_clears_completed_at(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->done()->create([
            'user_id' => $user->id, 'list_id' => $user->lists()->first()->id,
        ]);

        (new CompleteReminder())->run($r, false);

        $this->assertSame('open', $r->fresh()->status);
        $this->assertNull($r->fresh()->completed_at);
    }

    public function test_complete_is_idempotent(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        (new CompleteReminder())->run($r, true);
        $firstAt = $r->fresh()->completed_at;
        (new CompleteReminder())->run($r, true);
        $this->assertTrue($firstAt->equalTo($r->fresh()->completed_at));
    }
}
