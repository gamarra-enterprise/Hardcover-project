<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the staff panels behind two-step verification. Whoever has it turned on must pass the
 * challenge once per session; when the shop requires it (production), staff without it are sent to set it up.
 * Customers are not affected. Put it after the role check.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isStaff()) {
            return $next($request);
        }

        if (! $user->hasTwoFactor()) {
            return config('shop.require_two_factor')
                ? redirect()->route('security.show')->with('error', 'Para entrar al panel debes activar la verificación en dos pasos.')
                : $next($request);
        }

        if ($request->session()->get('two_factor_user') !== $user->id) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
