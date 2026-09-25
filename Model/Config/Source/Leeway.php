<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Leeway implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => __('None (current window only)')],
            ['value' => 1, 'label' => __('±30 seconds (recommended)')],
            ['value' => 2, 'label' => __('±60 seconds')],
        ];
    }
}
