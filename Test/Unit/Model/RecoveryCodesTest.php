<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Test\Unit\Model;

use MageOS\LoginTwoFactorAuth\Model\RecoveryCodes;
use PHPUnit\Framework\TestCase;

class RecoveryCodesTest extends TestCase
{
    private RecoveryCodes $codes;

    protected function setUp(): void
    {
        $this->codes = new RecoveryCodes();
    }

    public function testGenerate(): void
    {
        $generated = $this->codes->generate(10);
        $this->assertCount(10, $generated);
        $this->assertCount(10, array_unique($generated));
        foreach ($generated as $code) {
            $this->assertMatchesRegularExpression('/^[a-z2-9]{5}-[a-z2-9]{5}$/', $code);
            $this->assertTrue($this->codes->looksLikeRecoveryCode($code));
        }
    }

    public function testConsumeIsOneTimeAndFormatTolerant(): void
    {
        $generated = $this->codes->generate(3);
        $hashes = $this->codes->hashAll($generated);

        $remaining = $this->codes->consume($hashes, strtoupper(str_replace('-', ' ', $generated[1])));
        $this->assertIsArray($remaining);
        $this->assertCount(2, $remaining);
        $this->assertNull($this->codes->consume($remaining, $generated[1]));
        $this->assertNull($this->codes->consume($hashes, 'aaaaa-aaaaa'));
    }

    public function testSixDigitCodeIsNotARecoveryCode(): void
    {
        $this->assertFalse($this->codes->looksLikeRecoveryCode('123456'));
        $this->assertFalse($this->codes->looksLikeRecoveryCode('123 456'));
    }
}
