<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Account;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Activate extends AbstractAccount implements HttpPostActionInterface
{
    /**
     * @return Redirect
     */
    public function execute()
    {
        $customerId = $this->getCustomerId();
        $secret = (string) $this->customerSession->getData(self::SESSION_SETUP_SECRET);

        if ($secret === '' || $this->twoFactor->isActive($customerId)) {
            return $this->redirectToManage();
        }

        $codes = $this->twoFactor->activate($customerId, $secret, $this->getCode());
        if ($codes === null) {
            $this->messageManager->addErrorMessage(
                __('The code is not valid. Check that the time on your phone is correct and try again.')
            );
            return $this->redirectToManage();
        }

        $this->customerSession->unsetData(self::SESSION_SETUP_SECRET);
        $this->customerSession->setData(self::SESSION_NEW_CODES, $codes);
        $this->messageManager->addSuccessMessage(__('Two-factor authentication is now active on your account.'));

        return $this->redirectToManage();
    }
}
