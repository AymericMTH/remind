<?php

namespace App\Actions\Lists;

use App\Models\Reminder;
use App\Models\ReminderList;
use Illuminate\Support\Facades\DB;

class DeleteList
{
    public function run(ReminderList $list, DeleteStrategy $strategy): void
    {
        if ($list->is_inbox) {
            throw new \DomainException('Inbox cannot be deleted.');
        }

        DB::transaction(function () use ($list, $strategy) {
            if ($strategy === DeleteStrategy::MoveToInbox) {
                $inboxId = ReminderList::query()
                    ->where('user_id', $list->user_id)
                    ->where('is_inbox', true)
                    ->value('id');
                // Re-parent ALL children (including soft-deleted) — otherwise the
                // restrictOnDelete FK on reminders.list_id would block $list->delete().
                Reminder::withTrashed()
                    ->where('list_id', $list->id)
                    ->update(['list_id' => $inboxId]);
            } else {
                Reminder::withTrashed()->where('list_id', $list->id)->forceDelete();
            }

            $list->delete();
        });
    }
}
