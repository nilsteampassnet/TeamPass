<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function assert;
use const FILTER_VALIDATE_URL;
use function filter_var;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;
use Webauthn\Url;

/**
 * Normalizes Url objects to absolute HTTPS URLs.
 * Either returns the URL directly if already absolute, or generates it from a Symfony route.
 */
final readonly class UrlNormalizer implements NormalizerInterface
{
    public function __construct(
        private ?UrlGeneratorInterface $urlGenerator = null,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        assert($data instanceof Url);

        // If no URL generator available, return the path as-is
        if ($this->urlGenerator === null) {
            return $data->path;
        }

        // If the path is already a valid absolute URL (https://...), return it directly
        if (filter_var($data->path, FILTER_VALIDATE_URL) !== false) {
            return $data->path;
        }

        // Otherwise, try to generate an absolute URL from a Symfony route name
        try {
            return $this->urlGenerator->generate($data->path, $data->params, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (Throwable) {
            // If the URL cannot be generated, return the path as is
            return $data->path;
        }
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Url;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Url::class => true,
        ];
    }
}
