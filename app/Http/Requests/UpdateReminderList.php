<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderList extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('list'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'color' => ['sometimes', 'nullable', 'string', 'max:9'],
        ];
    }
}
