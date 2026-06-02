<?php

namespace App\Actions\Reminders;

use App\Models\Reminder;

class CompleteReminder
{
    public function run(Reminder $reminder, bool $done): Reminder
    {
        if ($done && $reminder->status === Reminder::STATUS_DONE) {
            return $reminder;
        }

        $reminder->status = $done ? Reminder::STATUS_DONE : Reminder::STATUS_OPEN;
        $reminder->completed_at = $done ? now() : null;
        $reminder->save();

        return $reminder;
    }
}
