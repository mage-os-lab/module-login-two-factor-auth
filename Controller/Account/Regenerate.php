<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Account;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Regenerate extends AbstractAccount implements HttpPostActionInterface
{
    /**
     * @return Redirect
     */
    public function execute()
    {
        $customerId = $this->getCustomerId();
        if (!$this->twoFactor->isActive($customerId)) {
            return $this->redirectToManage();
        }

        if (!$this->twoFactor->verifyTotp($customerId, $this->getCode())) {
            $this->messageManager->addErrorMessage(__('The authentication code is not valid.'));
            return $this->redirectToManage();
        }

        $this->customerSession->setData(self::SESSION_NEW_CODES, $this->twoFactor->regenerateRecoveryCodes($customerId));
        $this->messageManager->addSuccessMessage(__('New recovery codes generated. The old ones no longer work.'));

        return $this->redirectToManage();
    }
}
