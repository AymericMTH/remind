<?php

namespace Tests\Feature\Console;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurgeTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_soft_deleted_older_than_30_days(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();

        $old = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id]);
        $old->delete();
        $old->forceFill(['deleted_at' => Carbon::now()->subDays(31)])->save();

        $recent = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id]);
        $recent->delete();

        $this->artisan('reminders:purge-trash')->assertExitCode(0);

        $this->assertNull(Reminder::withTrashed()->find($old->id));
        $this->assertNotNull(Reminder::withTrashed()->find($recent->id));
    }
}
