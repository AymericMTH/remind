<?php

namespace App\Policies;

use App\Models\ReminderList;
use App\Models\User;

class ReminderListPolicy
{
    public function view(User $user, ReminderList $list): bool
    {
        return $list->user_id === $user->id;
    }

    public function update(User $user, ReminderList $list): bool
    {
        return $list->user_id === $user->id && ! $list->is_inbox;
    }

    public function delete(User $user, ReminderList $list): bool
    {
        return $list->user_id === $user->id && ! $list->is_inbox;
    }
}
