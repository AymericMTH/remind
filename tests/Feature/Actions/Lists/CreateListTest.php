<?php

namespace Tests\Feature\Actions\Lists;

use App\Actions\Lists\CreateList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateListTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_list_at_next_position(): void
    {
        $user = User::factory()->create(); // observer creates Inbox at position 0
        $list = (new CreateList)->run($user, ['name' => 'Work', 'color' => '#7aa2f7']);

        $this->assertSame('Work', $list->name);
        $this->assertSame('#7aa2f7', $list->color);
        $this->assertSame(1, $list->position);
        $this->assertFalse($list->is_inbox);
    }

    public function test_rejects_duplicate_name_per_user_case_insensitive(): void
    {
        $user = User::factory()->create();
        (new CreateList)->run($user, ['name' => 'Work']);

        $this->expectException(ValidationException::class);
        (new CreateList)->run($user, ['name' => 'work']);
    }

    public function test_two_users_can_each_have_a_list_named_work(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        (new CreateList)->run($a, ['name' => 'Work']);
        $list = (new CreateList)->run($b, ['name' => 'Work']);
        $this->assertSame($b->id, $list->user_id);
    }
}
