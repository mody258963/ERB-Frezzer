<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Comma-separated roles, e.g. role:admin,manager
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $allowed = array_filter(array_map('trim', explode(',', $roles)));

        foreach ($allowed as $role) {
            if ($user->role->value === $role) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'Insufficient permissions.');
    }
}
