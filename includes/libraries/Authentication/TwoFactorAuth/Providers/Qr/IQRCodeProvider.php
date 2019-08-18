<?php

namespace Authentication\TwoFactorAuth\Providers\Qr;

interface IQRCodeProvider
{
    public function getQRCodeImage($qrtext, $size, $proxyIP, $proxyPort);
    public function getMimeType();
}