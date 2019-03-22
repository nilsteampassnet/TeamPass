<?php

namespace Authentication\TwoFactorAuth\Providers\Qr;
require_once(dirname(__FILE__)."/BaseHTTPQRCodeProvider.php");

// https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=Example
// http://goqr.me/api/doc/create-qr-code/
class GoqrMeQRCodeProvider extends BaseHTTPQRCodeProvider 
{
    public $errorcorrectionlevel;
    public $margin;

    function __construct($verifyssl = false, $errorcorrectionlevel = 'L', $margin = 1) 
    {
        if (!is_bool($verifyssl))
            throw new \QRException('VerifySSL must be bool');

        $this->verifyssl = $verifyssl;
        
        $this->errorcorrectionlevel = $errorcorrectionlevel;
        $this->margin = $margin;
    }
    
    public function getMimeType() 
    {
        return 'image/png';
    }
    
    public function getQRCodeImage($qrtext, $size) 
    {
        return $this->getContent($this->getUrl($qrtext, $size));
    }
    
    public function getUrl($qrtext, $size) 
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?'
            . 'size=' . $size . 'x' . $size
            . '&ecc=' . $this->errorcorrectionlevel . '|' . $this->margin
            . '&data=' . rawurlencode($qrtext);
    }
}