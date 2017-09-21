<?php

namespace Authentication\TwoFactorAuth\Providers\Time;
require_once(dirname(__FILE__)."/ITimeProvider.php");

class LocalMachineTimeProvider implements ITimeProvider {
    public function getTime() {
        return time();
    }
}