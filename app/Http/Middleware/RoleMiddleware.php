<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            abort(401, 'Unauthenticated.');
        }

        $userRole = $request->user()->Role;

        if (! in_array($userRole, $roles, true)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
