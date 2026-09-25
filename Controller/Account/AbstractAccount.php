<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Account;

use Magento\Customer\Controller\AccountInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\LoginTwoFactorAuth\Model\TwoFactor;

/**
 * "My Account > Two-Factor Authentication". AccountInterface makes the core customer plugin
 * enforce a logged-in session on every action. Its aroundExecute() returns null for guests, so
 * execute() in subclasses must not declare a return type.
 */
abstract class AbstractAccount extends Action implements AccountInterface
{
    public const SESSION_SETUP_SECRET = 'mageos_tfa_setup_secret';
    public const SESSION_NEW_CODES = 'mageos_tfa_new_codes';

    public function __construct(
        Context $context,
        protected readonly Session $customerSession,
        protected readonly TwoFactor $twoFactor
    ) {
        parent::__construct($context);
    }

    public function dispatch(RequestInterface $request)
    {
        if (!$this->twoFactor->isEnabled()) {
            $this->_forward('noroute');
        }

        return parent::dispatch($request);
    }

    protected function getCustomerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }

    protected function redirectToManage(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('customer-2fa/account');
    }

    protected function getCode(): string
    {
        return trim((string) $this->getRequest()->getPost('code'));
    }
}
