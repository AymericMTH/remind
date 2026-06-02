<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // list_id is set in configure() so it shares the same user as user_id
            'title' => fake()->sentence(5),
            'notes' => null,
            'soft_due_date' => null,
            'context' => null,
            'status' => Reminder::STATUS_OPEN,
            'position' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Reminder $reminder) {
            if ($reminder->list_id === null) {
                $reminder->list_id = ReminderList::factory()
                    ->create(['user_id' => $reminder->user_id])
                    ->id;
            }
        });
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => Reminder::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }
}
