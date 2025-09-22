<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\DomCrawler\Crawler;

final class CrawlerSelectorTextContains extends Constraint
{
    private string $selector;
    private string $expectedText;
    private bool $hasNode = false;
    private string $nodeText;

    public function __construct(string $selector, string $expectedText)
    {
        $this->selector = $selector;
        $this->expectedText = $expectedText;
    }

    public function toString(): string
    {
        if ($this->hasNode) {
            return \sprintf('the text "%s" of the node matching selector "%s" contains "%s"', $this->nodeText, $this->selector, $this->expectedText);
        }

        return \sprintf('the Crawler has a node matching selector "%s"', $this->selector);
    }

    /**
     * @param Crawler $crawler
     */
    protected function matches($crawler): bool
    {
        $crawler = $crawler->filter($this->selector);
        if (!\count($crawler)) {
            $this->hasNode = false;

            return false;
        }

        $this->hasNode = true;
        $this->nodeText = $crawler->text(null, true);

        return str_contains($this->nodeText, $this->expectedText);
    }

    /**
     * @param Crawler $crawler
     */
    protected function failureDescription($crawler): string
    {
        return $this->toString();
    }
}
