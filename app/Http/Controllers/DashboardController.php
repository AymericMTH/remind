<?php

namespace App\Http\Controllers;

use App\Actions\Markdown\RenderNotes;
use App\Http\Controllers\Concerns\PresentsReminders;
use App\Models\ReminderList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use PresentsReminders;

    public function __invoke(Request $request, RenderNotes $renderer): Response
    {
        $user = $request->user();

        $lists = $user->lists()
            ->orderBy('position')
            ->withCount(['reminders as open_count' => fn ($q) => $q->where('status', 'open')])
            ->get(['id', 'name', 'color', 'position', 'is_inbox']);

        $selectedList = $this->resolveSelectedList($request, $user, $lists);

        $reminders = $selectedList->reminders()
            ->where('status', 'open')
            ->orderBy('position')
            ->get();

        $completed = $selectedList->reminders()
            ->where('status', 'done')
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get();

        return Inertia::render('dashboard/Index', [
            'lists' => $lists,
            'selectedList' => $selectedList->only(['id', 'name', 'color', 'is_inbox']),
            'reminders' => $reminders->map(fn ($r) => $this->presentReminder($r, $renderer))->values(),
            'completedReminders' => $completed->map(fn ($r) => $this->presentReminder($r, $renderer))->values(),
            'curatedColors' => config('remind.curated_colors'),
        ]);
    }

    private function resolveSelectedList(Request $request, $user, $lists): ReminderList
    {
        $id = $request->query('list');

        if ($id) {
            $match = $lists->firstWhere('id', (int) $id);
            if ($match) {
                return $match;
            }
        }

        return $lists->firstWhere('is_inbox', true);
    }
}
