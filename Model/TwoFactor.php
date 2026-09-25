<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model;

use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Customer-facing 2FA operations: enrollment, challenge verification, recovery codes.
 */
class TwoFactor
{
    public function __construct(
        private readonly Config $config,
        private readonly Storage $storage,
        private readonly Totp $totp,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly QrCode $qrCode
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function isActive(int $customerId): bool
    {
        return $this->storage->isActive($customerId);
    }

    /**
     * Challenge required at login: feature enabled on this store and customer opted in.
     */
    public function isRequired(int $customerId): bool
    {
        return $customerId > 0 && $this->config->isEnabled() && $this->storage->isActive($customerId);
    }

    public function getActivatedAt(int $customerId): ?string
    {
        return $this->storage->load($customerId)['created_at'] ?? null;
    }

    public function getRemainingRecoveryCodes(int $customerId): int
    {
        return count($this->storage->load($customerId)['recovery_codes'] ?? []);
    }

    public function generateSecret(): string
    {
        return $this->totp->generateSecret();
    }

    public function getProvisioningUri(string $secret, CustomerInterface $customer): string
    {
        return $this->totp->getProvisioningUri($secret, (string) $customer->getEmail(), $this->config->getIssuer());
    }

    public function getQrCodeDataUri(string $secret, CustomerInterface $customer): string
    {
        return $this->qrCode->getDataUri($this->getProvisioningUri($secret, $customer));
    }

    /**
     * Confirms enrollment with a first valid code from the app.
     *
     * @return string[]|null the plain recovery codes to show once, or null if the code is wrong
     */
    public function activate(int $customerId, string $secret, string $code): ?array
    {
        $step = $this->totp->verify($secret, $code, $this->config->getLeeway());
        if ($step === null) {
            return null;
        }

        $codes = $this->recoveryCodes->generate($this->config->getRecoveryCodesCount());
        $this->storage->activate($customerId, $secret, $this->recoveryCodes->hashAll($codes), $step);

        return $codes;
    }

    /**
     * Verifies a TOTP code only (no recovery codes): used for sensitive account actions.
     */
    public function verifyTotp(int $customerId, string $code): bool
    {
        $data = $this->storage->load($customerId);
        if ($data === null) {
            return false;
        }
        $step = $this->totp->verify($data['secret'], $code, $this->config->getLeeway(), $data['last_timestep']);
        if ($step === null) {
            return false;
        }
        try {
            $this->storage->updateLastTimestep($customerId, $step);
        } catch (\RuntimeException) {
            return false;
        }

        return true;
    }

    /**
     * Verifies a TOTP code or, failing that, consumes a recovery code.
     */
    public function verifyTotpOrRecoveryCode(int $customerId, string $code): bool
    {
        if ($this->recoveryCodes->looksLikeRecoveryCode($code)) {
            return $this->consumeRecoveryCode($customerId, $code);
        }

        return $this->verifyTotp($customerId, $code);
    }

    /**
     * @return string[]
     */
    public function regenerateRecoveryCodes(int $customerId): array
    {
        $codes = $this->recoveryCodes->generate($this->config->getRecoveryCodesCount());
        $this->storage->updateRecoveryCodes($customerId, $this->recoveryCodes->hashAll($codes));

        return $codes;
    }

    public function deactivate(int $customerId): bool
    {
        return $this->storage->delete($customerId);
    }

    private function consumeRecoveryCode(int $customerId, string $code): bool
    {
        $data = $this->storage->load($customerId);
        if ($data === null) {
            return false;
        }
        $remaining = $this->recoveryCodes->consume($data['recovery_codes'], $code);
        if ($remaining === null) {
            return false;
        }

        return $this->storage->updateRecoveryCodes($customerId, $remaining, $data['recovery_codes']);
    }
}
