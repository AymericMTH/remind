<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RemindUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('remind.bootstrap_email');
        $name = config('remind.bootstrap_name');
        $password = config('remind.bootstrap_password');

        if (! $email || ! $password) {
            $this->command->warn('Skipping RemindUserSeeder: set REMIND_USER_EMAIL and REMIND_USER_PASSWORD in .env');
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name ?? 'Re:Mind user', 'password' => Hash::make($password)],
        );
    }
}
