<?php
namespace Cerberus\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class CerberusAuthenticate extends Middleware
{
    protected function unauthenticated($request, array $guards)
    {
        abort(response()->json([
            'message' => 'Unauthenticated.',
        ], 401));
    }

    protected function redirectTo($request): ?string
    {
        return null;
    }
}
