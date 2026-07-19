<?php

namespace Modules\UserManagement\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP secrets and recovery codes are stored on the user (both already
 * `encrypted`-cast) — this service only ever handles the plaintext values
 * transiently, in memory, for the single request that needs them.
 */
class TwoFactorAuthService
{
    protected Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function qrCodeUrl(string $businessName, string $email, string $secret): string
    {
        return $this->engine->getQRCodeUrl($businessName, $email, $secret);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code);
    }

    /**
     * @return string[] 10 plaintext recovery codes, shown to the user
     *                  exactly once at generation time.
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 10))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }

    /**
     * @param  string[]  $plainCodes
     * @return string[] bcrypt hashes — what actually gets stored.
     */
    public function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(fn ($code) => Hash::make($code), $plainCodes);
    }

    /**
     * @param  string[]  $hashedCodes
     * @return int|null the index of the matched (now-consumed) code, so the
     *                  caller can remove it — a recovery code is single-use.
     */
    public function matchRecoveryCode(string $input, array $hashedCodes): ?int
    {
        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($input, $hash)) {
                return $index;
            }
        }

        return null;
    }
}
