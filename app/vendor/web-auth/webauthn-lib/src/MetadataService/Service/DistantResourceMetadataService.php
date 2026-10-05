<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Service;

use ParagonIE\ConstantTime\Base64;
use Psr\EventDispatcher\EventDispatcherInterface;
use function sprintf;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Event\CanDispatchEvents;
use Webauthn\Event\MetadataStatementFound;
use Webauthn\Event\NullEventDispatcher;
use Webauthn\Exception\MetadataStatementLoadingException;
use Webauthn\Exception\MissingMetadataStatementException;
use Webauthn\MetadataService\Statement\MetadataStatement;

final class DistantResourceMetadataService implements MetadataService, CanDispatchEvents
{
    private ?MetadataStatement $statement = null;

    private EventDispatcherInterface $dispatcher;

    private readonly SerializerInterface $serializer;

    /**
     * @param array<string, string> $additionalHeaderParameters
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $uri,
        private readonly bool $isBase64Encoded = false,
        private readonly array $additionalHeaderParameters = [],
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? (new WebauthnSerializerFactory(
            AttestationStatementSupportManager::create()
        ))->create();
        $this->dispatcher = new NullEventDispatcher();
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->dispatcher = $eventDispatcher;
    }

    /**
     * @param array<string, string> $additionalHeaderParameters
     */
    public static function create(
        HttpClientInterface $httpClient,
        string $uri,
        bool $isBase64Encoded = false,
        array $additionalHeaderParameters = [],
        ?SerializerInterface $serializer = null
    ): self {
        return new self($httpClient, $uri, $isBase64Encoded, $additionalHeaderParameters, $serializer);
    }

    public function list(): iterable
    {
        $this->loadData();
        $this->statement !== null || throw MetadataStatementLoadingException::create();
        $aaguid = $this->statement->aaguid;
        if ($aaguid === null) {
            yield from [];
        } else {
            yield from [$aaguid];
        }
    }

    public function has(string $aaguid): bool
    {
        $this->loadData();
        $this->statement !== null || throw MetadataStatementLoadingException::create();

        return $aaguid === $this->statement->aaguid;
    }

    public function get(string $aaguid): MetadataStatement
    {
        $this->loadData();
        $this->statement !== null || throw MetadataStatementLoadingException::create();

        if ($aaguid === $this->statement->aaguid) {
            $this->dispatcher->dispatch(MetadataStatementFound::create($this->statement));

            return $this->statement;
        }

        throw MissingMetadataStatementException::create($aaguid);
    }

    private function loadData(): void
    {
        if ($this->statement !== null) {
            return;
        }

        $content = $this->fetch();
        if ($this->isBase64Encoded) {
            $content = Base64::decode($content, true);
        }
        $this->statement = $this->serializer->deserialize($content, MetadataStatement::class, JsonEncoder::FORMAT);
    }

    private function fetch(): string
    {
        $response = $this->httpClient->request('GET', $this->uri, [
            'headers' => $this->additionalHeaderParameters,
        ]);
        $response->getStatusCode() === 200 || throw MetadataStatementLoadingException::create(sprintf(
            'Unable to contact the server. Response code is %d',
            $response->getStatusCode()
        ));

        $content = $response->getContent();
        $content !== '' || throw MetadataStatementLoadingException::create(
            'Unable to contact the server. The response has no content'
        );

        return $content;
    }
}
