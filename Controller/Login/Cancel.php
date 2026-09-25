<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Login;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use MageOS\LoginTwoFactorAuth\Model\LoginGuard;

class Cancel implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly LoginGuard $loginGuard
    ) {
    }

    public function execute(): Redirect
    {
        $this->loginGuard->clearPending();

        return $this->redirectFactory->create()->setPath('customer/account/login');
    }
}
