<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Account;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Starts enrollment: a fresh secret lives in the session until the customer confirms a code.
 */
class Setup extends AbstractAccount implements HttpPostActionInterface
{
    /**
     * @return Redirect
     */
    public function execute()
    {
        if (!$this->twoFactor->isActive($this->getCustomerId())) {
            $this->customerSession->setData(self::SESSION_SETUP_SECRET, $this->twoFactor->generateSecret());
        }

        return $this->redirectToManage();
    }
}
