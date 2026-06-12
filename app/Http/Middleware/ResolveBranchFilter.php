<?php

namespace App\Http\Middleware;

use App\Support\BranchVisibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchFilter
{
    /**
     * Resolves optional admin branch filter from ?branch_id= for the whole request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $request->attributes->set(
                'resolved_branch_id',
                BranchVisibility::resolveBranchId(
                    $request->user(),
                    BranchVisibility::requestedBranchId($request),
                ),
            );
        }

        return $next($request);
    }
}
