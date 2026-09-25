<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Block\Adminhtml\Customer\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Customer\Block\Adminhtml\Edit\GenericButton;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use MageOS\LoginTwoFactorAuth\Model\Storage;

/**
 * Customer edit page: lets support turn off 2FA for a customer who lost their phone
 * and their recovery codes.
 */
class ResetTwoFactorButton extends GenericButton implements ButtonProviderInterface
{
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly Storage $storage,
        private readonly AuthorizationInterface $authorization,
        private readonly Escaper $escaper
    ) {
        parent::__construct($context, $registry);
    }

    public function getButtonData(): array
    {
        $customerId = (int) $this->getCustomerId();
        if (!$customerId
            || !$this->authorization->isAllowed('MageOS_LoginTwoFactorAuth::reset')
            || !$this->storage->isActive($customerId)
        ) {
            return [];
        }

        return [
            'label' => __('Reset 2FA'),
            'class' => 'reset-2fa',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $this->escaper->escapeJs(__(
                    'Turn off two-factor authentication for this customer? '
                    . 'They will sign in with password only until they enable it again.'
                )),
                $this->getUrl('customer2fa/customer/reset', ['customer_id' => $customerId])
            ),
            'sort_order' => 55,
        ];
    }
}
