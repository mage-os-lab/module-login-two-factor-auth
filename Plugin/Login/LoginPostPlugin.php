<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Plugin\Login;

use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use MageOS\LoginTwoFactorAuth\Model\LoginGuard;

/**
 * Standard login form: password accepted but 2FA pending → go to the code challenge.
 */
class LoginPostPlugin
{
    public function __construct(
        private readonly LoginGuard $loginGuard,
        private readonly AccountRedirect $accountRedirect,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function aroundExecute(LoginPost $subject, callable $proceed)
    {
        // LoginPost clears the "redirect after login" cookie on its way out: keep its value.
        $redirectCookie = $this->accountRedirect->getRedirectCookie();
        $this->loginGuard->clearPending();

        $result = $proceed();

        if (!$this->loginGuard->hasPending()) {
            return $result;
        }
        $this->loginGuard->setPendingRedirect($redirectCookie ?: null);

        return $this->redirectFactory->create()->setPath('customer-2fa/login');
    }
}
