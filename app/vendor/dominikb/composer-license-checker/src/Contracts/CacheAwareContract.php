<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Contracts;

use Symfony\Contracts\Cache\CacheInterface;

interface CacheAwareContract
{
    public function setCache(CacheInterface $cache): void;
}
