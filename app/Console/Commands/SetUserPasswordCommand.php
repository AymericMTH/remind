<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetUserPasswordCommand extends Command
{
    protected $signature = 'user:password
        {identifier : Username or email of the user}
        {--password= : The new password (will be prompted if omitted)}
        {--clear : Clear the password (passwordless login)}';

    protected $description = 'Set or clear a user password by username or email.';

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');

        $user = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if ($user === null) {
            $this->error("No user found for [{$identifier}].");

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $user->forceFill(['password' => null])->save();
            $this->info("Cleared password for [{$user->username}]".($user->email ? " <{$user->email}>" : '').'.');

            return self::SUCCESS;
        }

        $password = (string) ($this->option('password') ?? $this->secret('New password'));

        if ($password === '') {
            $this->error('Password may not be empty. Use --clear to remove the password instead.');

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();
        $this->info("Updated password for [{$user->username}]".($user->email ? " <{$user->email}>" : '').'.');

        return self::SUCCESS;
    }
}
