<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model;

use OTPHP\TOTP as OtpTotp;
use ParagonIE\ConstantTime\Base32;

/**
 * RFC 6238 TOTP (SHA1, 6 digits, 30s) — the variant every authenticator app supports.
 */
class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;

    /**
     * 160-bit secret (RFC 4226 recommendation), base32 without padding: 32 chars, easy to type.
     */
    public function generateSecret(): string
    {
        return Base32::encodeUpperUnpadded(random_bytes(20));
    }

    public function getProvisioningUri(string $secret, string $account, string $issuer): string
    {
        $totp = $this->create($secret);
        $totp->setLabel($account);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri();
    }

    /**
     * Returns the matched time-step, or null when the code is invalid. Codes whose time-step is
     * not greater than $lastTimestep are rejected so an observed code can't be replayed.
     */
    public function verify(string $secret, string $code, int $leeway = 1, int $lastTimestep = 0, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }

        $totp = $this->create($secret);
        $current = intdiv($now ?? time(), self::PERIOD);
        $matched = null;
        // Walk every window so the timing does not reveal which one matched.
        for ($offset = -$leeway; $offset <= $leeway; $offset++) {
            $step = $current + $offset;
            if (hash_equals($totp->at($step * self::PERIOD), $code) && $step > $lastTimestep) {
                $matched = $step;
            }
        }

        return $matched;
    }

    private function create(string $secret): OtpTotp
    {
        $totp = OtpTotp::createFromSecret($secret);
        $totp->setPeriod(self::PERIOD);
        $totp->setDigits(self::DIGITS);
        $totp->setDigest('sha1');

        return $totp;
    }
}
