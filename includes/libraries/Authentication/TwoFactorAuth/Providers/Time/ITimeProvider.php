<?php

namespace Authentication\TwoFactorAuth\Providers\Time;

interface ITimeProvider
{
    public function getTime();
}