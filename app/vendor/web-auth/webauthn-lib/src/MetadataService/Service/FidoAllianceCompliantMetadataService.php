<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Service;

use function array_key_exists;
use function is_array;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use function sprintf;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Event\CanDispatchEvents;
use Webauthn\Event\MetadataStatementFound;
use Webauthn\Event\NullEventDispatcher;
use Webauthn\Exception\MetadataStatementLoadingException;
use Webauthn\Exception\MissingMetadataStatementException;
use Webauthn\MetadataService\CertificateChain\CertificateChainValidator;
use Webauthn\MetadataService\CertificateChain\CertificateToolbox;
use Webauthn\MetadataService\Statement\MetadataStatement;
use Webauthn\MetadataService\Statement\StatusReport;

final class FidoAllianceCompliantMetadataService implements MetadataService, CanDispatchEvents
{
    private bool $loaded = false;

    /**
     * @var MetadataStatement[]
     */
    private array $statements = [];

    /**
     * @var array<string, StatusReport[]>
     */
    private array $statusReports = [];

    private EventDispatcherInterface $dispatcher;

    private readonly SerializerInterface $serializer;

    /**
     * @param array<string, mixed> $additionalHeaderParameters
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $uri,
        private readonly array $additionalHeaderParameters = [],
        private readonly ?CertificateChainValidator $certificateChainValidator = null,
        private readonly ?string $rootCertificateUri = null,
        ?SerializerInterface $serializer = null,
    ) {
        if (! class_exists(CompactSerializer::class)) {
            throw new LogicException(
                'The "web-token/jwt-library" package is required to use this service. Please run "composer require web-token/jwt-library".'
            );
        }
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
     * @param array<string, mixed> $additionalHeaderParameters
     */
    public static function create(
        HttpClientInterface $httpClient,
        string $uri,
        array $additionalHeaderParameters = [],
        ?CertificateChainValidator $certificateChainValidator = null,
        ?string $rootCertificateUri = null,
        ?SerializerInterface $serializer = null,
    ): self {
        return new self(
            $httpClient,
            $uri,
            $additionalHeaderParameters,
            $certificateChainValidator,
            $rootCertificateUri,
            $serializer,
        );
    }

    /**
     * @return string[]
     */
    public function list(): iterable
    {
        $this->loadData();

        yield from array_keys($this->statements);
    }

    public function has(string $aaguid): bool
    {
        $this->loadData();

        return array_key_exists($aaguid, $this->statements);
    }

    public function get(string $aaguid): MetadataStatement
    {
        $this->loadData();
        array_key_exists($aaguid, $this->statements) || throw MissingMetadataStatementException::create($aaguid);
        $mds = $this->statements[$aaguid];
        $this->dispatcher->dispatch(MetadataStatementFound::create($mds));

        return $mds;
    }

    /**
     * @return StatusReport[]
     */
    public function getStatusReports(string $aaguid): iterable
    {
        $this->loadData();

        return $this->statusReports[$aaguid] ?? [];
    }

    private function loadData(): void
    {
        if ($this->loaded) {
            return;
        }

        $content = $this->fetch($this->uri, $this->additionalHeaderParameters);
        $jwtCertificates = [];
        try {
            $payload = $this->getJwsPayload($content, $jwtCertificates);
            $this->validateCertificates(...$jwtCertificates);
            /** @var MetadataBLOBPayload $blob */
            $blob = $this->serializer->deserialize($payload, MetadataBLOBPayload::class, JsonEncoder::FORMAT);
            foreach ($blob->entries as $entry) {
                $mds = $entry->metadataStatement;
                if ($mds !== null && $entry->aaguid !== null) {
                    $this->statements[$entry->aaguid] = $mds;
                    $this->statusReports[$entry->aaguid] = $entry->statusReports;
                }
            }
        } catch (Throwable) {
            // Nothing to do
        } finally {
            $this->loaded = true;
        }
    }

    /**
     * @param array<string, mixed> $headerParameters
     */
    private function fetch(string $uri, array $headerParameters): string
    {
        $response = $this->httpClient->request('GET', $uri, [
            'headers' => $headerParameters,
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

    /**
     * @param string[] $rootCertificates
     */
    private function getJwsPayload(string $token, array &$rootCertificates): string
    {
        $jws = (new CompactSerializer())->unserialize($token);
        $jws->countSignatures() === 1 || throw MetadataStatementLoadingException::create(
            'Invalid response from the metadata service. Only one signature shall be present.'
        );
        $signature = $jws->getSignature(0);
        $payload = $jws->getPayload();
        $payload !== '' || throw MetadataStatementLoadingException::create(
            'Invalid response from the metadata service. The token payload is empty.'
        );
        $header = $signature->getProtectedHeader();
        array_key_exists('alg', $header) || throw MetadataStatementLoadingException::create(
            'The "alg" parameter is missing.'
        );
        array_key_exists('x5c', $header) || throw MetadataStatementLoadingException::create(
            'The "x5c" parameter is missing.'
        );
        is_array($header['x5c']) || throw MetadataStatementLoadingException::create(
            'The "x5c" parameter should be an array.'
        );
        /** @var string[] $x5c */
        $x5c = $header['x5c'];
        $key = JWKFactory::createFromX5C($x5c);
        $rootCertificates = $x5c;

        $verifier = new JWSVerifier(new AlgorithmManager([new ES256(), new RS256()]));
        $isValid = $verifier->verifyWithKey($jws, $key, 0);
        $isValid || throw MetadataStatementLoadingException::create(
            'Invalid response from the metadata service. The token signature is invalid.'
        );
        $payload = $jws->getPayload();
        $payload !== null || throw MetadataStatementLoadingException::create(
            'Invalid response from the metadata service. The payload is missing.'
        );

        return $payload;
    }

    private function validateCertificates(string ...$untrustedCertificates): void
    {
        if ($this->certificateChainValidator === null || $this->rootCertificateUri === null) {
            return;
        }
        $untrustedCertificates = CertificateToolbox::fixPEMStructures($untrustedCertificates);
        $rootCertificate = CertificateToolbox::convertDERToPEM($this->fetch($this->rootCertificateUri, []));
        $this->certificateChainValidator->check($untrustedCertificates, [$rootCertificate]);
    }
}
