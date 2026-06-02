<?php

namespace App\Actions\Reminders;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateReminder
{
    public function __construct(private NormalizeContext $normalizeContext = new NormalizeContext) {}

    /**
     * @param  array{
     *     list_id:int,
     *     title:string,
     *     notes?:?string,
     *     soft_due_date?:?string,
     *     context?:?array
     * }  $attrs
     */
    public function run(User $user, array $attrs): Reminder
    {
        $data = Validator::make($attrs, [
            'list_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'soft_due_date' => ['nullable', 'date'],
            'context' => ['nullable', 'array'],
        ])->validate();

        $list = ReminderList::query()
            ->where('id', $data['list_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $list) {
            throw ValidationException::withMessages([
                'list_id' => 'List not found.',
            ]);
        }

        $position = (int) $list->reminders()->max('position') + 1;

        return $user->reminders()->create([
            'list_id' => $list->id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'soft_due_date' => $data['soft_due_date'] ?? null,
            'context' => $this->normalizeContext->run($data['context'] ?? null),
            'status' => Reminder::STATUS_OPEN,
            'position' => $position,
        ]);
    }
}
