<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\ViewModel;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageOS\LoginTwoFactorAuth\Controller\Account\AbstractAccount;
use MageOS\LoginTwoFactorAuth\Model\TwoFactor;

class Account implements ArgumentInterface
{
    public const STATE_INACTIVE = 'inactive';
    public const STATE_SETUP = 'setup';
    public const STATE_ACTIVE = 'active';

    private ?array $newCodes = null;

    public function __construct(
        private readonly Session $customerSession,
        private readonly TwoFactor $twoFactor,
        private readonly UrlInterface $url,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function getState(): string
    {
        if ($this->twoFactor->isActive($this->getCustomerId())) {
            return self::STATE_ACTIVE;
        }

        return $this->getSetupSecret() !== '' ? self::STATE_SETUP : self::STATE_INACTIVE;
    }

    public function getSetupSecret(): string
    {
        return (string) $this->customerSession->getData(AbstractAccount::SESSION_SETUP_SECRET);
    }

    /**
     * Secret split in groups of 4 for manual entry.
     */
    public function getFormattedSecret(): string
    {
        return implode(' ', str_split($this->getSetupSecret(), 4));
    }

    public function getQrCodeDataUri(): string
    {
        return $this->twoFactor->getQrCodeDataUri($this->getSetupSecret(), $this->getCustomer());
    }

    public function getProvisioningUri(): string
    {
        return $this->twoFactor->getProvisioningUri($this->getSetupSecret(), $this->getCustomer());
    }

    /**
     * Recovery codes just generated: returned once, then dropped from the session.
     *
     * @return string[]
     */
    public function getNewRecoveryCodes(): array
    {
        if ($this->newCodes === null) {
            $this->newCodes = (array) $this->customerSession->getData(AbstractAccount::SESSION_NEW_CODES, true);
        }

        return $this->newCodes;
    }

    public function getRemainingRecoveryCodes(): int
    {
        return $this->twoFactor->getRemainingRecoveryCodes($this->getCustomerId());
    }

    public function getActivatedAt(): string
    {
        $date = $this->twoFactor->getActivatedAt($this->getCustomerId());

        return $date ? $this->timezone->formatDate($date, \IntlDateFormatter::MEDIUM) : '';
    }

    public function getActionUrl(string $action): string
    {
        return $this->url->getUrl('customer-2fa/account/' . $action);
    }

    private function getCustomerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }

    private function getCustomer(): CustomerInterface
    {
        return $this->customerSession->getCustomerData();
    }
}
