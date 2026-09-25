<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Login implements ArgumentInterface
{
    public function __construct(
        private readonly UrlInterface $url
    ) {
    }

    public function getVerifyUrl(): string
    {
        return $this->url->getUrl('customer-2fa/login/verify');
    }

    public function getCancelUrl(): string
    {
        return $this->url->getUrl('customer-2fa/login/cancel');
    }
}
