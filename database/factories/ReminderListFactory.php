<?php

namespace Database\Factories;

use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderListFactory extends Factory
{
    protected $model = ReminderList::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
            'position' => 0,
            'is_inbox' => false,
        ];
    }

    public function inbox(): static
    {
        return $this->state(fn () => ['name' => 'Inbox', 'is_inbox' => true]);
    }
}
