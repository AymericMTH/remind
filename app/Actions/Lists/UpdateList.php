<?php

namespace App\Actions\Lists;

use App\Models\ReminderList;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateList
{
    /**
     * @param  array{name?:string,color?:?string}  $attrs
     */
    public function run(ReminderList $list, array $attrs): ReminderList
    {
        $data = Validator::make($attrs, [
            'name' => ['sometimes', 'string', 'max:80'],
            'color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#?(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})$/'],
        ])->validate();

        if (isset($data['name']) && mb_strtolower($data['name']) !== mb_strtolower($list->name)) {
            $duplicate = ReminderList::query()
                ->where('user_id', $list->user_id)
                ->where('id', '!=', $list->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'A list with this name already exists.',
                ]);
            }
        }

        $list->update($data);

        return $list;
    }
}
