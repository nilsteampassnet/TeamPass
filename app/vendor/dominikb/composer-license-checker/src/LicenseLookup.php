<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use DateTimeImmutable;
use Dominikb\ComposerLicenseChecker\Contracts\LicenseLookup as LicenseLookupContract;
use Dominikb\ComposerLicenseChecker\Exceptions\NoLookupPossibleException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Cache\CacheInterface;

class LicenseLookup implements LicenseLookupContract
{
    const API_HOST = 'https://tldrlegal.com';

    /** @var ClientInterface */
    protected $http;
    /** @var CacheInterface */
    protected $cache;
    /** @var string[] */
    private static $noLookup = [
        'none',
        'proprietary',
    ];

    public function __construct(ClientInterface $http, CacheInterface $cache = null)
    {
        $this->http = $http;
        $this->cache = $cache ?? new FilesystemAdapter('FilesystemCache', 3600, __DIR__.'/../.cache');
    }

    public function lookUp(string $licenseName): License
    {
        return $this->cache->get($licenseName, function () use ($licenseName) {
            try {
                $detailsPageUrl = $this->queryForDetailPageUrl($licenseName);

                $license = $this->resolveLicenseInformation($licenseName, $detailsPageUrl);
            } catch (NoLookupPossibleException $exception) {
                $license = new NoLookupLicenses($licenseName);
            }

            return $license;
        });
    }

    /**
     * @param  string  $licenseShortName
     * @return string
     *
     * @throws NoLookupPossibleException
     */
    private function queryForDetailPageUrl(string $licenseShortName): string
    {
        if (in_array($licenseShortName, self::$noLookup)) {
            throw new NoLookupPossibleException;
        }

        $searchUrl = sprintf('%s/search?query=%s', static::API_HOST, $licenseShortName);

        try {
            $response = $this->http->request('get', $searchUrl);
        } catch (GuzzleException $exception) {
            throw new NoLookupPossibleException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $crawler = $this->makeCrawler($response->getBody()->getContents());

        $headings = $crawler->filter('div.search-result-items .cc-semibold')->extract(['_text']);
        $links = $crawler->filter('div.search-result-items .c-link-arrow')->extract(['href']);

        $zipped = array_map(null, $headings, $links);

        $relativeUrl = $this->findBestMatch($zipped, $licenseShortName);

        return static::API_HOST.$relativeUrl;
    }

    private function makeCrawler(string $html): Crawler
    {
        return new Crawler($html);
    }

    /**
     * @param  string  $licenseShortName
     * @param  string  $detailsPageUrl
     * @return License
     *
     * @throws NoLookupPossibleException
     */
    private function resolveLicenseInformation(string $licenseShortName, string $detailsPageUrl): License
    {
        try {
            $response = $this->http->request('get', $detailsPageUrl);
            $pageContent = $response->getBody()->getContents();

            $crawler = $this->makeCrawler($pageContent);

            $license = (new License($licenseShortName))
                ->setCan($this->extractCans($crawler))
                ->setCannot($this->extractCannots($crawler))
                ->setMust($this->extractMusts($crawler))
                ->setSource($detailsPageUrl)
                ->setCreatedAt(new DateTimeImmutable);

            return $license;
        } catch (GuzzleException $exception) {
            throw new NoLookupPossibleException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function extractCans(Crawler $crawler): array
    {
        return $this->extractListByIndex($crawler, 1);
    }

    private function extractCannots(Crawler $crawler): array
    {
        return $this->extractListByIndex($crawler, 3);
    }

    private function extractMusts(Crawler $crawler): array
    {
        return $this->extractListByIndex($crawler, 5);
    }

    // Index is the offset as children of div.features
    private function extractListByIndex(Crawler $crawler, $index): array
    {
        $headings = $crawler->filter('div.c-feature:nth-child('.$index.') .c-text-md.cc-semibold')
                            ->each(function (Crawler $crawler) {
                                return $crawler->getNode(0)->textContent;
                            });

        $bodies = $crawler->filter('div.c-feature:nth-child('.$index.') .c-text-sm')
                          ->each(function (Crawler $crawler) {
                              return $crawler->getNode(0)->textContent;
                          });

        return array_combine($headings, $bodies);
    }

    /**
     * Find the best matching link by comparing the similarity of the link and text.
     *
     * @param  array  $zipped
     * @param  string  $licenseShortName
     * @return string
     */
    private function findBestMatch(array $zipped, string $licenseShortName): string
    {
        $bestMatch = 0;
        $matchingLink = '';

        foreach ($zipped as [$title, $link]) {
            $titleMatch = similar_text($title, $licenseShortName);
            $linkMatch = similar_text($link, $licenseShortName);

            $totalMatch = $titleMatch + $linkMatch;

            if ($totalMatch > $bestMatch) {
                $bestMatch = $totalMatch;
                $matchingLink = $link;
            }
        }

        return $matchingLink;
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }
}
