<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\LoginTwoFactorAuth\Model\Storage;

class Reset extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_LoginTwoFactorAuth::reset';

    public function __construct(
        Context $context,
        private readonly Storage $storage
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        if ($customerId && $this->storage->delete($customerId)) {
            $this->messageManager->addSuccessMessage(__('Two-factor authentication has been turned off for this customer.'));
        } else {
            $this->messageManager->addNoticeMessage(__('Two-factor authentication was not active for this customer.'));
        }

        return $this->resultRedirectFactory->create()->setPath('customer/index/edit', ['id' => $customerId]);
    }
}
