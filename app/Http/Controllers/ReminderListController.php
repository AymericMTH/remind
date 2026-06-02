<?php

namespace App\Http\Controllers;

use App\Actions\Lists\CreateList;
use App\Actions\Lists\DeleteList;
use App\Actions\Lists\DeleteStrategy;
use App\Actions\Lists\ReorderLists;
use App\Http\Requests\StoreReminderList;
use App\Http\Requests\UpdateReminderList;
use App\Models\ReminderList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReminderListController extends Controller
{
    public function store(StoreReminderList $request, CreateList $action): RedirectResponse
    {
        $action->run($request->user(), $request->validated());

        return redirect()->back();
    }

    public function update(UpdateReminderList $request, ReminderList $list): RedirectResponse
    {
        $list->update($request->validated());

        return redirect()->back();
    }

    public function destroy(Request $request, ReminderList $list, DeleteList $action): RedirectResponse
    {
        Gate::authorize('delete', $list);
        $strategy = DeleteStrategy::from($request->input('strategy', 'move_to_inbox'));
        $action->run($list, $strategy);

        return redirect()->route('dashboard');
    }

    public function reorder(Request $request, ReorderLists $action): RedirectResponse
    {
        $action->run(
            $request->user(),
            collect($request->input('order', []))->map(fn ($x) => (int) $x)->all(),
        );

        return redirect()->back();
    }
}
