<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Plugin\Login;

use Magento\Framework\App\RequestInterface;
use MageOS\LoginTwoFactorAuth\Model\LoginGuard;

/**
 * Threecommerce OneStepCheckout (Magewire) inline login: send the customer to the challenge,
 * then back to the checkout. Only wired when that module's class exists (see etc/frontend/di.xml).
 */
class OneStepCheckoutLoginPlugin
{
    public function __construct(
        private readonly LoginGuard $loginGuard,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @param \Threecommerce\OneStepCheckout\Magewire\Checkout $subject
     */
    public function aroundLogin($subject, callable $proceed): void
    {
        $this->loginGuard->clearPending();
        $proceed();

        if (!$this->loginGuard->hasPending()) {
            return;
        }
        // Magewire posts to its own endpoint: the referer is the checkout page.
        $this->loginGuard->setPendingRedirect((string) $this->request->getServer('HTTP_REFERER'), true);
        $subject->redirect('customer-2fa/login');
    }
}
