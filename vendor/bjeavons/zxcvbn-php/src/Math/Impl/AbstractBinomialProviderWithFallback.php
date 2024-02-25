<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Math\Impl;

abstract class AbstractBinomialProviderWithFallback extends AbstractBinomialProvider
{
    /**
     * @var AbstractBinomialProvider|null
     */
    private $fallback = null;

    protected function calculate(int $n, int $k): float
    {
        return  $this->tryCalculate($n, $k) ?? $this->getFallbackProvider()->calculate($n, $k);
    }

    abstract protected function tryCalculate(int $n, int $k): ?float;

    abstract protected function initFallbackProvider(): AbstractBinomialProvider;

    protected function getFallbackProvider(): AbstractBinomialProvider
    {
        if ($this->fallback === null) {
            $this->fallback = $this->initFallbackProvider();
        }

        return $this->fallback;
    }
}