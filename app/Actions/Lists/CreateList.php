<?php

namespace App\Actions\Lists;

use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateList
{
    /**
     * @param  array{name:string,color?:?string}  $attrs
     */
    public function run(User $user, array $attrs): ReminderList
    {
        $data = Validator::make($attrs, [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:9', 'regex:/^#?(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})$/'],
        ])->validate();

        $existing = $user->lists()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'A list with this name already exists.',
            ]);
        }

        $nextPosition = (int) $user->lists()->max('position') + 1;

        return $user->lists()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'position' => $nextPosition,
            'is_inbox' => false,
        ]);
    }
}
