<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Plugin;

use MageOS\LoginTwoFactorAuth\Model\LoginGuard;

/**
 * Core flows that re-establish an already authenticated customer (signed store-switch redirect,
 * admin "Login as Customer") must not be stopped by the 2FA challenge.
 */
class TrustedLoginPlugin
{
    public function __construct(
        private readonly LoginGuard $loginGuard
    ) {
    }

    /**
     * @see \Magento\Customer\Model\StoreSwitcher\RedirectDataPostprocessor::process()
     */
    public function aroundProcess($subject, callable $proceed, ...$args)
    {
        return $this->loginGuard->trusted(static fn () => $proceed(...$args));
    }

    /**
     * @see \Magento\Store\Controller\Store\SwitchRequest::execute()
     * @see \Magento\LoginAsCustomer\Model\AuthenticateCustomerBySecret::execute()
     */
    public function aroundExecute($subject, callable $proceed, ...$args)
    {
        return $this->loginGuard->trusted(static fn () => $proceed(...$args));
    }
}
