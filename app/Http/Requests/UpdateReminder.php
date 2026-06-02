<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminder extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('reminder'));
    }

    public function rules(): array
    {
        return [
            'list_id' => ['sometimes', 'integer'],
            'title' => ['sometimes', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'soft_due_date' => ['sometimes', 'nullable', 'date'],
            'context' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
