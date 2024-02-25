<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Math\Impl;

class BinomialProviderPhp73Gmp extends AbstractBinomialProvider
{
    /**
     * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
     * @noinspection PhpComposerExtensionStubsInspection
     */
    protected function calculate(int $n, int $k): float
    {
        return (float)gmp_strval(gmp_binomial($n, $k));
    }
}