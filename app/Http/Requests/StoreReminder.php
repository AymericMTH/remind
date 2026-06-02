<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReminder extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'list_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'soft_due_date' => ['nullable', 'date'],
            'context' => ['nullable', 'array'],
        ];
    }
}
