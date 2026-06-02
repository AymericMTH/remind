<?php

namespace App\Mcp\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Http\Request;
use RuntimeException;

class SingleUserMiddleware
{
    public function __construct(private AuthManager $auth) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $user = User::sole();
        } catch (ModelNotFoundException|MultipleRecordsFoundException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $this->auth->guard('web')->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
