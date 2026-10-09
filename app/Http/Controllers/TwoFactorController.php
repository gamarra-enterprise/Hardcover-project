<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\TwoFactor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Two-step verification for staff: set up, confirm, turn off, and the sign-in challenge. */
class TwoFactorController extends Controller
{
    public function show(Request $request, TwoFactor $twoFactor): View
    {
        $user = $this->staff($request);

        return view('security.show', [
            'user' => $user,
            'enabled' => $user->hasTwoFactor(),
            'pending' => ! $user->hasTwoFactor() && $user->two_factor_secret !== null,
            'qr' => ! $user->hasTwoFactor() && $user->two_factor_secret !== null ? $twoFactor->qrSvg($user) : null,
            'recoveryCodes' => $request->session()->pull('recovery_codes'),
            'remaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function start(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->staff($request);
        abort_if($user->hasTwoFactor(), 409);

        $twoFactor->start($user);

        return redirect()->route('security.show');
    }

    public function confirm(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $codes = $twoFactor->confirm($user, $data['code']);

        if ($codes === []) {
            return back()->withErrors(['code' => 'Ese código no es correcto. Revisa la hora de tu teléfono e inténtalo de nuevo.']);
        }

        $request->session()->put('two_factor_user', $user->id);
        ActivityLog::record('security.2fa_enabled', 'Activó la verificación en dos pasos');

        return redirect()->route('security.show')->with('recovery_codes', $codes);
    }

    public function disable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['password' => ['required', 'string']]);

        if (! $twoFactor->passwordMatches($user, $data['password'])) {
            return back()->withErrors(['password' => 'La contraseña no es correcta.']);
        }

        $twoFactor->disable($user);
        $request->session()->forget('two_factor_user');
        ActivityLog::record('security.2fa_disabled', 'Desactivó la verificación en dos pasos');

        return redirect()->route('security.show')->with('notice', 'Verificación en dos pasos desactivada.');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $this->staff($request);

        if (! $user->hasTwoFactor() || $request->session()->get('two_factor_user') === $user->id) {
            return redirect()->route('dashboard');
        }

        return view('security.challenge');
    }

    public function verify(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $key = 'two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Demasiados intentos. Espera un minuto.']);
        }

        if (! $user->hasTwoFactor() || ! $twoFactor->verify($user, $data['code'])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => 'Código incorrecto o ya usado.']);
        }

        RateLimiter::clear($key);
        $request->session()->put('two_factor_user', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    private function staff(Request $request)
    {
        abort_unless($request->user()?->isStaff(), 403);

        return $request->user();
    }
}
