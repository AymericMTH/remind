<?php

namespace App\Http\Controllers;

use App\Actions\Reminders\CompleteReminder;
use App\Actions\Reminders\CreateReminder;
use App\Actions\Reminders\ReorderReminders;
use App\Actions\Reminders\UpdateReminder as UpdateAction;
use App\Http\Requests\StoreReminder;
use App\Http\Requests\UpdateReminder as UpdateRequest;
use App\Models\Reminder;
use App\Models\ReminderList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReminderController extends Controller
{
    public function store(StoreReminder $request, CreateReminder $action): RedirectResponse
    {
        $action->run($request->user(), $request->validated());

        return redirect()->back();
    }

    public function update(UpdateRequest $request, Reminder $reminder, UpdateAction $action): RedirectResponse
    {
        $action->run($reminder, $request->validated());

        return redirect()->back();
    }

    public function destroy(Reminder $reminder): RedirectResponse
    {
        Gate::authorize('delete', $reminder);
        $reminder->delete();

        return redirect()->back();
    }

    public function complete(Request $request, Reminder $reminder, CompleteReminder $action): RedirectResponse
    {
        Gate::authorize('update', $reminder);
        $action->run($reminder, (bool) $request->input('done', true));

        return redirect()->back();
    }

    public function reorder(Request $request, ReorderReminders $action): RedirectResponse
    {
        $list = ReminderList::query()
            ->where('id', (int) $request->input('list_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $action->run($list, collect($request->input('order', []))->map(fn ($x) => (int) $x)->all());

        return redirect()->back();
    }
}
