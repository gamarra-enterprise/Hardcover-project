<?php

namespace App\Services;

use App\Models\User;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-step verification with time-based codes (the kind Google Authenticator, Authy or 1Password
 * generate). The secret and the recovery codes are stored encrypted. A code can be used once:
 * the time step of the last accepted code is kept and anything older or equal is refused.
 */
class TwoFactor
{
    public function __construct(private readonly Google2FA $google = new Google2FA) {}

    /** Start enrolling: a new secret, not active until the first code is confirmed. */
    public function start(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->google->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /** @return list<string> the recovery codes, to show once, or an empty list if the code is wrong */
    public function confirm(User $user, string $code): array
    {
        if ($user->two_factor_secret === null || $user->hasTwoFactor() || ! $this->accepts($user, $code)) {
            return [];
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $this->hashed($codes)])->save();

        return $codes;
    }

    /** True for a current code (once) or an unused recovery code (consumed). */
    public function verify(User $user, string $input): bool
    {
        $input = trim($input);

        if (preg_match('/^\d{6}$/', $input)) {
            return $this->accepts($user, $input);
        }

        return $this->useRecoveryCode($user, $input);
    }

    public function disable(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
    }

    public function passwordMatches(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /** The address the authenticator app reads from the QR. */
    public function uri(User $user): string
    {
        return $this->google->getQRCodeUrl(config('app.name'), $user->email, $user->two_factor_secret);
    }

    public function qrSvg(User $user): string
    {
        return (new QRCode(new QROptions(['outputType' => QROutputInterface::MARKUP_SVG, 'outputBase64' => false, 'addQuietzone' => true, 'svgViewBoxSize' => 200])))
            ->render($this->uri($user));
    }

    private function accepts(User $user, string $code): bool
    {
        $step = $this->google->verifyKeyNewer($user->two_factor_secret, $code, $user->two_factor_last_step, window: 1);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step === true ? $this->google->getTimestamp() : $step])->save();

        return true;
    }

    private function useRecoveryCode(User $user, string $input): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $input = strtolower($input);

        foreach ($codes as $i => $hash) {
            if (Hash::check($input, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtolower(Str::random(5).'-'.Str::random(5)))->all();
    }

    /** @param  list<string>  $codes @return list<string> */
    private function hashed(array $codes): array
    {
        return array_map(fn (string $c) => Hash::make($c), $codes);
    }
}
