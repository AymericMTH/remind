<?php

namespace App\Actions\Reminders;

use App\Models\ReminderList;
use Illuminate\Support\Facades\DB;

class ReorderReminders
{
    /** @param array<int,int> $orderedIds */
    public function run(ReminderList $list, array $orderedIds): void
    {
        $owned = $list->reminders()->whereIn('id', $orderedIds)->pluck('id')->all();
        $ids = array_values(array_intersect($orderedIds, $owned));
        DB::transaction(function () use ($ids, $list) {
            foreach ($ids as $i => $id) {
                $list->reminders()->whereKey($id)->update(['position' => $i]);
            }
        });
    }
}
