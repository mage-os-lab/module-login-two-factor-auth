<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Account;

use Magento\Customer\Model\AuthenticationInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use MageOS\LoginTwoFactorAuth\Model\TwoFactor;

/**
 * Turning 2FA off requires both factors: current password and a code (or a recovery code).
 */
class Disable extends AbstractAccount implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        Session $customerSession,
        TwoFactor $twoFactor,
        private readonly AuthenticationInterface $authentication
    ) {
        parent::__construct($context, $customerSession, $twoFactor);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $customerId = $this->getCustomerId();
        if (!$this->twoFactor->isActive($customerId)) {
            return $this->redirectToManage();
        }

        try {
            $this->authentication->authenticate($customerId, (string) $this->getRequest()->getPost('password'));
        } catch (LocalizedException) {
            $this->messageManager->addErrorMessage(__('The password you entered is incorrect.'));
            return $this->redirectToManage();
        }

        if (!$this->twoFactor->verifyTotpOrRecoveryCode($customerId, $this->getCode())) {
            $this->authentication->processAuthenticationFailure($customerId);
            $this->messageManager->addErrorMessage(__('The authentication code is not valid.'));
            return $this->redirectToManage();
        }

        $this->twoFactor->deactivate($customerId);
        $this->messageManager->addSuccessMessage(__('Two-factor authentication has been turned off.'));

        return $this->redirectToManage();
    }
}
