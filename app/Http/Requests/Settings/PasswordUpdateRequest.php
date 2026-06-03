<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'password' => $this->optionalPasswordRules(),
        ];

        if ($this->user()->password !== null) {
            $rules['current_password'] = $this->currentPasswordRules();
        }

        return $rules;
    }
}
