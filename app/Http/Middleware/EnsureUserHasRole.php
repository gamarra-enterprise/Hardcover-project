<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin') or 'role:admin,customer'.
 * A super admin passes every role check, in line with the Gate::before rule.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->role === UserRole::SUPER_ADMIN || in_array($user->role->value, $roles, true)),
            403,
        );

        return $next($request);
    }
}
