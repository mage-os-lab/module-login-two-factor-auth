<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Account;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Cancel extends AbstractAccount implements HttpPostActionInterface
{
    /**
     * @return Redirect
     */
    public function execute()
    {
        $this->customerSession->unsetData(self::SESSION_SETUP_SECRET);

        return $this->redirectToManage();
    }
}
