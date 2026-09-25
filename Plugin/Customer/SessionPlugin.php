<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Plugin\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use MageOS\LoginTwoFactorAuth\Model\LoginGuard;
use MageOS\LoginTwoFactorAuth\Model\TwoFactor;

/**
 * Fail-closed interception: every storefront login goes through the customer session, so a
 * customer with 2FA active is never logged in here. The session is left anonymous and a pending
 * challenge is recorded instead; login entry points we know about then redirect to it.
 */
class SessionPlugin
{
    public function __construct(
        private readonly TwoFactor $twoFactor,
        private readonly LoginGuard $loginGuard
    ) {
    }

    public function aroundSetCustomerDataAsLoggedIn(Session $subject, callable $proceed, CustomerInterface $customer)
    {
        if ($this->mustChallenge((int) $customer->getId())) {
            return $subject;
        }

        return $proceed($customer);
    }

    public function aroundSetCustomerAsLoggedIn(Session $subject, callable $proceed, Customer $customer)
    {
        if ($this->mustChallenge((int) $customer->getId())) {
            return $subject;
        }

        return $proceed($customer);
    }

    private function mustChallenge(int $customerId): bool
    {
        if ($this->loginGuard->isTrusted() || !$this->twoFactor->isRequired($customerId)) {
            return false;
        }
        $this->loginGuard->startPending($customerId);

        return true;
    }
}
