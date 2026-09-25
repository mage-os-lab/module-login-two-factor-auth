<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Login;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use MageOS\LoginTwoFactorAuth\Model\LoginGuard;

/**
 * Second step of the sign-in: asks for the authenticator (or recovery) code.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly LoginGuard $loginGuard,
        private readonly Session $customerSession
    ) {
    }

    public function execute(): ResultInterface
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account');
        }
        if (!$this->loginGuard->hasPending()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Two-Factor Authentication'));

        return $page;
    }
}
