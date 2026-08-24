<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Restrict a route to users whose role matches one of the given roles.
     *
     * Usage: ->middleware('role:pto_administrator') or ->middleware('role:lgu,establishment')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = collect($roles)
            ->map(fn (string $role) => UserRole::from($role))
            ->contains($user?->role);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
