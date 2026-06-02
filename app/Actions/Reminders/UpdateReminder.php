<?php

namespace App\Actions\Reminders;

use App\Models\Reminder;
use App\Models\ReminderList;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateReminder
{
    public function __construct(private NormalizeContext $normalizeContext = new NormalizeContext()) {}

    public function run(Reminder $reminder, array $attrs): Reminder
    {
        $data = Validator::make($attrs, [
            'title' => ['sometimes', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'soft_due_date' => ['sometimes', 'nullable', 'date'],
            'context' => ['sometimes', 'nullable', 'array'],
            'list_id' => ['sometimes', 'integer'],
        ])->validate();

        if (isset($data['list_id'])) {
            $list = ReminderList::query()
                ->where('id', $data['list_id'])
                ->where('user_id', $reminder->user_id)
                ->first();
            if (! $list) {
                throw ValidationException::withMessages([
                    'list_id' => 'List not found.',
                ]);
            }
            $reminder->list_id = $list->id;
        }

        if (array_key_exists('title', $data)) {
            $reminder->title = $data['title'];
        }
        if (array_key_exists('notes', $data)) {
            $reminder->notes = $data['notes'];
        }
        if (array_key_exists('soft_due_date', $data)) {
            $reminder->soft_due_date = $data['soft_due_date'];
        }
        if (array_key_exists('context', $data)) {
            $reminder->context = $this->normalizeContext->run($data['context']);
        }

        $reminder->save();

        return $reminder;
    }
}
