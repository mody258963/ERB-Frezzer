<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * @param  string  ...$roles  e.g. middleware('role:admin,manager')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        foreach ($roles as $role) {
            $role = trim($role);
            if ($role !== '' && $user->role->value === $role) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'Insufficient permissions.');
    }
}
