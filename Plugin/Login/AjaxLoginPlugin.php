<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Plugin\Login;

use Magento\Customer\Controller\Ajax\Login;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use MageOS\LoginTwoFactorAuth\Model\LoginGuard;

/**
 * Luma authentication popup: the JS follows `redirectUrl` on a non-error response.
 */
class AjaxLoginPlugin
{
    public function __construct(
        private readonly LoginGuard $loginGuard,
        private readonly AccountRedirect $accountRedirect,
        private readonly JsonFactory $jsonFactory,
        private readonly UrlInterface $url,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function aroundExecute(Login $subject, callable $proceed)
    {
        $redirectCookie = $this->accountRedirect->getRedirectCookie();
        $this->loginGuard->clearPending();

        $result = $proceed();

        if (!$this->loginGuard->hasPending()) {
            return $result;
        }
        // Same target the popup would have used: the redirect cookie, else reload the current page.
        $useCookie = $redirectCookie && !$this->scopeConfig->getValue('customer/startup/redirect_dashboard');
        $this->loginGuard->setPendingRedirect(
            $useCookie ? $redirectCookie : (string) $subject->getRequest()->getServer('HTTP_REFERER'),
            true
        );

        return $this->jsonFactory->create()->setData([
            'errors' => false,
            'message' => __('Please enter your two-factor authentication code.'),
            'redirectUrl' => $this->url->getUrl('customer-2fa/login'),
        ]);
    }
}
