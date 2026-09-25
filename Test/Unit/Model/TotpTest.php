<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Test\Unit\Model;

use MageOS\LoginTwoFactorAuth\Model\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238 Appendix B secret ("12345678901234567890") in base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private Totp $totp;

    protected function setUp(): void
    {
        $this->totp = new Totp();
    }

    public function testGenerateSecretIsBase32Of160Bits(): void
    {
        $secret = $this->totp->generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertNotSame($secret, $this->totp->generateSecret());
    }

    public function testRfcVector(): void
    {
        // RFC 6238: T=59 → 94287082 (8 digits); the 6-digit variant is the last 6 digits.
        $this->assertSame(1, $this->totp->verify(self::RFC_SECRET, '287082', 0, 0, 59));
    }

    public function testLeewayAcceptsAdjacentWindow(): void
    {
        $this->assertNull($this->totp->verify(self::RFC_SECRET, '287082', 0, 0, 59 + 30));
        $this->assertSame(1, $this->totp->verify(self::RFC_SECRET, '287082', 1, 0, 59 + 30));
    }

    public function testReplayIsRejected(): void
    {
        $this->assertNull($this->totp->verify(self::RFC_SECRET, '287082', 1, 1, 59));
    }

    public function testMalformedCodes(): void
    {
        $this->assertNull($this->totp->verify(self::RFC_SECRET, '', 1, 0, 59));
        $this->assertNull($this->totp->verify(self::RFC_SECRET, '28708a', 1, 0, 59));
        $this->assertSame(1, $this->totp->verify(self::RFC_SECRET, '287 082', 1, 0, 59));
    }

    public function testProvisioningUri(): void
    {
        $uri = $this->totp->getProvisioningUri(self::RFC_SECRET, 'mario@example.com', 'Demo Store');
        $this->assertStringStartsWith('otpauth://totp/Demo%20Store%3Amario%40example.com?', $uri);
        $this->assertStringContainsString('secret=' . self::RFC_SECRET, $uri);
        $this->assertStringContainsString('issuer=Demo%20Store', $uri);
    }
}
