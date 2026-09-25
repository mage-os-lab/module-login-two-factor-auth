<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    public const XML_ENABLED = 'customer_2fa/general/enabled';
    public const XML_ISSUER = 'customer_2fa/general/issuer';
    public const XML_LEEWAY = 'customer_2fa/general/leeway';
    public const XML_MAX_ATTEMPTS = 'customer_2fa/general/max_attempts';
    public const XML_RECOVERY_CODES = 'customer_2fa/general/recovery_codes';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isEnabled($scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    public function getIssuer($scopeCode = null): string
    {
        $issuer = trim((string) $this->scopeConfig->getValue(self::XML_ISSUER, ScopeInterface::SCOPE_STORE, $scopeCode));
        if ($issuer === '') {
            $issuer = trim((string) $this->scopeConfig->getValue(
                'general/store_information/name',
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            ));
        }
        if ($issuer === '') {
            $issuer = (string) $this->storeManager->getStore($scopeCode)->getFrontendName();
        }

        // ':' separates issuer and account in the otpauth label and is rejected by otphp.
        return str_replace(':', ' ', $issuer !== '' ? $issuer : 'Store');
    }

    public function getLeeway($scopeCode = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_LEEWAY, ScopeInterface::SCOPE_STORE, $scopeCode);

        return max(0, min(2, $value));
    }

    public function getMaxAttempts($scopeCode = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_MAX_ATTEMPTS, ScopeInterface::SCOPE_STORE, $scopeCode);

        return $value > 0 ? $value : 5;
    }

    public function getRecoveryCodesCount($scopeCode = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_RECOVERY_CODES, ScopeInterface::SCOPE_STORE, $scopeCode);

        return max(1, min(20, $value ?: 10));
    }
}
