<?php

namespace App\Actions\Lists;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReorderLists
{
    /** @param array<int,int> $orderedIds */
    public function run(User $user, array $orderedIds): void
    {
        $owned = $user->lists()->whereIn('id', $orderedIds)->pluck('id')->all();
        $ids = array_values(array_intersect($orderedIds, $owned));
        DB::transaction(function () use ($ids, $user) {
            foreach ($ids as $i => $id) {
                $user->lists()->whereKey($id)->update(['position' => $i]);
            }
        });
    }
}
