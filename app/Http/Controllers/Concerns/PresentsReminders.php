<?php

namespace App\Http\Controllers\Concerns;

use App\Actions\Markdown\RenderNotes;
use App\Models\Reminder;

trait PresentsReminders
{
    /**
     * @return array<string,mixed>
     */
    protected function presentReminder(Reminder $r, RenderNotes $renderer): array
    {
        return [
            'id' => $r->id,
            'list_id' => $r->list_id,
            'title' => $r->title,
            'notes' => $r->notes,
            'notes_html' => $renderer->run($r->notes),
            'soft_due_date' => $r->soft_due_date?->toDateString(),
            'context' => $r->context,
            'status' => $r->status,
            'completed_at' => $r->completed_at?->toIso8601String(),
            'position' => $r->position,
        ];
    }
}
