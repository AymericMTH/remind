<?php

namespace App\Observers;

use App\Models\ReminderList;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        ReminderList::query()->create([
            'user_id' => $user->id,
            'name' => 'Inbox',
            'is_inbox' => true,
            'position' => 0,
        ]);
    }
}
