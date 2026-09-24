<?php

namespace App\Members\Support;

use App\Members\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * An account's two-factor authentication, as one shared piece of logic.
 *
 * Same columns, same TOTP, same recovery-code format and burn-on-use as
 * App\Members\Controllers\TwoFactorController, so a secret set up here is
 * checked there (the login challenge) and vice versa. Written for surfaces
 * that must manage 2FA in their own look (the federation dashboard) without
 * bouncing the user through the platform's security page.
 *
 * One deliberate difference: beginSetup() refuses an account that already has
 * 2FA on. Starting setup on the platform page resets the secret and switches
 * 2FA OFF with no code asked (registered in HOUSE-CLEANING to fix there).
 *
 * TwoFactorController still carries its own copy — registered to be pointed at
 * this class as a separate, deliberate step (RULE #1).
 */
class TwoFactor
{
    private Google2FA $g;

    public function __construct()
    {
        $this->g = new Google2FA;
    }

    /** @return array{secret: string, qr: string}|null  null when 2FA is already on */
    public function beginSetup(User $user): ?array
    {
        if ($user->hasTwoFactorEnabled()) {
            return null;
        }
        $secret = $this->g->generateSecretKey();
        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_confirmed_at = null;
        $user->save();

        return ['secret' => $secret, 'qr' => $this->qr((string) $user->email, $secret)];
    }

    /** The pending (unconfirmed) secret's QR again, e.g. after a wrong code. */
    public function pending(User $user): ?array
    {
        if ($user->hasTwoFactorEnabled() || ! $user->two_factor_secret) {
            return null;
        }
        $secret = decrypt($user->two_factor_secret);

        return ['secret' => $secret, 'qr' => $this->qr((string) $user->email, $secret)];
    }

    /** Confirm with the first code. @return array<int, string>|null recovery codes, shown once */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->hasTwoFactorEnabled() || ! $user->two_factor_secret || ! $this->g->verifyKey(decrypt($user->two_factor_secret), trim($code))) {
            return null;
        }
        $codes = $this->newCodes();
        $user->two_factor_recovery_codes = encrypt(json_encode($codes));
        $user->two_factor_confirmed_at = now();
        $user->save();

        return $codes;
    }

    /** @return array<int, string>|null */
    public function regenerate(User $user, string $code): ?array
    {
        if (! $user->hasTwoFactorEnabled() || ! $this->g->verifyKey(decrypt($user->two_factor_secret), trim($code))) {
            return null;
        }
        $codes = $this->newCodes();
        $user->two_factor_recovery_codes = encrypt(json_encode($codes));
        $user->save();

        return $codes;
    }

    /** Turn it off — a current code or a recovery code required. */
    public function disable(User $user, string $code): bool
    {
        $code = trim($code);
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }
        if (! $this->g->verifyKey(decrypt($user->two_factor_secret), $code) && ! $this->burnRecoveryCode($user, $code)) {
            return false;
        }
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return true;
    }

    private function qr(string $email, string $secret): string
    {
        $url = $this->g->getQRCodeUrl(config('app.name'), $email, $secret);

        return (new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd)))->writeString($url);
    }

    private function newCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtoupper(Str::random(5)).'-'.strtoupper(Str::random(5)))->all();
    }

    private function burnRecoveryCode(User $user, string $code): bool
    {
        if (! $user->two_factor_recovery_codes) {
            return false;
        }
        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
        $i = array_search($code, $codes, true);
        if ($i === false) {
            return false;
        }
        array_splice($codes, $i, 1);
        $user->two_factor_recovery_codes = encrypt(json_encode($codes));
        $user->save();

        return true;
    }
}
