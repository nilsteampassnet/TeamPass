<?php

namespace Authentication\TwoFactorAuth\Providers\Time;
require_once(dirname(__FILE__)."/ITimeProvider.php");

class ConvertUnixTimeDotComTimeProvider implements ITimeProvider
{
    public function getTime() {
        $json = @json_decode(
            @file_get_contents('http://www.convert-unix-time.com/api?timestamp=now')
        );
        if ($json === null || !is_int($json->timestamp))
            throw new \TimeException('Unable to retrieve time from convert-unix-time.com');
        return $json->timestamp;
    }
}