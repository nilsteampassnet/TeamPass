<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Service;

use function assert;
use const DIRECTORY_SEPARATOR;
use function file_get_contents;
use InvalidArgumentException;
use function is_array;
use function sprintf;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\MetadataStatementLoadingException;
use Webauthn\MetadataService\Statement\MetadataStatement;

final class FolderResourceMetadataService implements MetadataService
{
    private readonly SerializerInterface $serializer;

    public function __construct(
        private string $rootPath,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? (new WebauthnSerializerFactory(
            AttestationStatementSupportManager::create()
        ))->create();
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        is_dir($this->rootPath) || throw new InvalidArgumentException('The given parameter is not a valid folder.');
        is_readable($this->rootPath) || throw new InvalidArgumentException(
            'The given parameter is not a valid folder.'
        );
    }

    public static function create(string $rootPath, ?SerializerInterface $serializer = null): self
    {
        return new self($rootPath, $serializer);
    }

    public function list(): iterable
    {
        $files = glob($this->rootPath . DIRECTORY_SEPARATOR . '*');
        is_array($files) || throw MetadataStatementLoadingException::create('Unable to read files.');
        foreach ($files as $file) {
            if (is_dir($file) || ! is_readable($file)) {
                continue;
            }

            yield basename($file);
        }
    }

    public function has(string $aaguid): bool
    {
        $filename = $this->rootPath . DIRECTORY_SEPARATOR . $aaguid;

        return is_file($filename) && is_readable($filename);
    }

    public function get(string $aaguid): MetadataStatement
    {
        $this->has($aaguid) || throw new InvalidArgumentException(sprintf(
            'The MDS with the AAGUID "%s" does not exist.',
            $aaguid
        ));
        $filename = $this->rootPath . DIRECTORY_SEPARATOR . $aaguid;
        $content = file_get_contents($filename);
        assert($content !== false, 'The file exists and is readable.');
        $data = trim($content);
        $mds = $this->serializer->deserialize($data, MetadataStatement::class, JsonEncoder::FORMAT);
        $mds->aaguid !== null || throw MetadataStatementLoadingException::create('Invalid Metadata Statement.');

        return $mds;
    }
}
