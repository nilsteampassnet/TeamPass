<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function array_key_exists;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webauthn\MetadataService\Statement\ExtensionDescriptor;

final class ExtensionDescriptorDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @var array{id: string, tag?: ?int, data?: ?string, failIfUnknown?: bool, fail_if_unknown?: bool} $data */
        if (array_key_exists('fail_if_unknown', $data)) {
            $data['failIfUnknown'] = $data['fail_if_unknown'];
            unset($data['fail_if_unknown']);
        }

        return ExtensionDescriptor::create(...$data);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === ExtensionDescriptor::class;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            ExtensionDescriptor::class => true,
        ];
    }
}
