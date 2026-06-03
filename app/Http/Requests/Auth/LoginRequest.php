<?php

namespace App\Http\Requests\Auth;

use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /**
     * Allow an empty password so that passwordless local accounts can authenticate.
     *
     * @return array<string, string>
     */
    public function rules()
    {
        return [
            Fortify::username() => 'required|string',
            'password' => 'nullable|string',
            'remember' => 'sometimes',
        ];
    }
}
