<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxAutoCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_creates_an_inbox_list(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1, $user->lists()->count());
        $inbox = $user->lists()->first();
        $this->assertTrue($inbox->is_inbox);
        $this->assertSame('Inbox', $inbox->name);
    }

    public function test_inbox_is_created_at_position_zero(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, $user->lists()->where('is_inbox', true)->first()->position);
    }

    public function test_each_user_gets_their_own_inbox(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertNotSame(
            $a->lists()->where('is_inbox', true)->first()->id,
            $b->lists()->where('is_inbox', true)->first()->id,
        );
    }
}
