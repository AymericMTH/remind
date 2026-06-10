<?php

namespace Tests\Feature\Http;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_shows_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/Index')
                ->where('selectedList.id', $inbox->id)
                ->has('lists', 1)
                ->has('reminders', 0)
                ->has('completedReminders', 0));
    }

    public function test_query_param_selects_list(): void
    {
        $user = User::factory()->create();
        $work = ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);
        Reminder::factory()->count(3)->create(['user_id' => $user->id, 'list_id' => $work->id]);
        Reminder::factory()->done()->create(['user_id' => $user->id, 'list_id' => $work->id]);

        $this->actingAs($user)
            ->get("/dashboard?list={$work->id}")
            ->assertInertia(fn ($page) => $page
                ->where('selectedList.id', $work->id)
                ->has('reminders', 3)
                ->has('completedReminders', 1));
    }

    public function test_invalid_list_id_falls_back_to_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();

        $this->actingAs($user)
            ->get('/dashboard?list=9999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('selectedList.id', $inbox->id));
    }

    public function test_other_users_list_falls_back_to_inbox(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bsList = ReminderList::factory()->create(['user_id' => $b->id]);
        $aInbox = $a->lists()->where('is_inbox', true)->first();

        $this->actingAs($a)
            ->get("/dashboard?list={$bsList->id}")
            ->assertInertia(fn ($page) => $page->where('selectedList.id', $aInbox->id));
    }

    public function test_reminder_payload_includes_notes_html(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $list->id,
            'notes' => '**hi**',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('reminders.0.notes_html', fn ($html) => str_contains($html, '<strong>hi</strong>')));
    }

    public function test_global_reminders_absent_on_normal_load(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->missing('globalReminders'));
    }

    public function test_global_reminders_present_on_partial_reload_and_user_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $work = ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);
        Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $user->lists()->first()->id,
            'title' => 'mine 1',
        ]);
        Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $work->id,
            'title' => 'mine 2',
        ]);
        Reminder::factory()->create([
            'user_id' => $other->id,
            'list_id' => $other->lists()->first()->id,
            'title' => 'not mine',
        ]);

        $version = hash_file('xxh128', public_path('build/manifest.json'));

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'dashboard/Index',
                'X-Inertia-Partial-Data' => 'globalReminders',
            ])
            ->get('/dashboard')
            ->assertOk();

        $payload = $response->json();
        $this->assertArrayHasKey('globalReminders', $payload['props']);
        $items = $payload['props']['globalReminders'];
        $this->assertCount(2, $items);
        $titles = collect($items)->pluck('title')->all();
        $this->assertEqualsCanonicalizing(['mine 1', 'mine 2'], $titles);
        foreach ($items as $item) {
            $this->assertArrayHasKey('list', $item);
            $this->assertArrayHasKey('id', $item['list']);
        }
    }
}
