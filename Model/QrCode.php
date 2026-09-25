<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model;

use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\SvgWriter;

class QrCode
{
    /**
     * SVG data URI, rendered server-side so the secret never leaves the page for a third-party service.
     */
    public function getDataUri(string $content, int $size = 220): string
    {
        $qrCode = new EndroidQrCode(data: $content, size: $size, margin: 8);

        return (new SvgWriter())->write($qrCode)->getDataUri();
    }
}
