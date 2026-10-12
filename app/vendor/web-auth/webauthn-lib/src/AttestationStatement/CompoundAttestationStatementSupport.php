<?php

declare(strict_types=1);

namespace Webauthn\AttestationStatement;

use function array_key_exists;
use function count;
use InvalidArgumentException;
use function is_array;
use Psr\EventDispatcher\EventDispatcherInterface;
use function sprintf;
use Throwable;
use Webauthn\AuthenticatorData;
use Webauthn\Event\AttestationStatementLoaded;
use Webauthn\Event\CanDispatchEvents;
use Webauthn\Event\NullEventDispatcher;
use Webauthn\Exception\AttestationStatementLoadingException;
use Webauthn\Exception\AttestationStatementVerificationException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Compound Attestation Statement Support
 *
 * Supports the "compound" attestation format as defined in WebAuthn Level 3
 * @see https://w3c.github.io/webauthn/#sctn-compound-attestation
 *
 * The compound format allows multiple attestation statements of different formats
 * to be included together. This is useful for hybrid authenticators or scenarios
 * where multiple attestation proofs are needed.
 */
final class CompoundAttestationStatementSupport implements AttestationStatementSupport, CanDispatchEvents, AttestationStatementSupportManagerAwareInterface
{
    use AttestationStatementSupportManagerAwareTrait;

    private const ATTESTATION_TYPE_TRUST_ORDER = [
        AttestationStatement::TYPE_ATTCA,
        AttestationStatement::TYPE_ANONCA,
        AttestationStatement::TYPE_BASIC,
        AttestationStatement::TYPE_SELF,
        AttestationStatement::TYPE_NONE,
    ];

    private EventDispatcherInterface $dispatcher;

    private ?float $ratio = 1.0;

    private ?int $minimum = null;

    public function __construct()
    {
        $this->dispatcher = new NullEventDispatcher();
        $this->attestationStatementSupportManager = AttestationStatementSupportManager::create();
    }

    public function setMinimum(int $minimum): void
    {
        $this->checkRules(null, $minimum);
        $this->minimum = $minimum;
        $this->ratio = null;
    }

    public function setRatio(float $ratio): void
    {
        $this->checkRules($ratio, null);
        $this->ratio = $ratio;
        $this->minimum = null;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->dispatcher = $eventDispatcher;
    }

    public static function create(): self
    {
        return new self();
    }

    public function name(): string
    {
        return 'compound';
    }

    /**
     * @param array<string, mixed> $attestation
     */
    public function load(array $attestation): AttestationStatement
    {
        array_key_exists('attStmt', $attestation) || throw AttestationStatementLoadingException::create($attestation);
        is_array($attestation['attStmt']) || throw AttestationStatementLoadingException::create($attestation);

        /** @var array<string, mixed> $attStmt */
        $attStmt = $attestation['attStmt'];
        $loadedAttestations = [];
        foreach ($attStmt as $index => $nestedAttestationObject) {
            is_array($nestedAttestationObject) || throw AttestationStatementLoadingException::create(
                $attestation,
                sprintf('Attestation at index %s must be an array.', (string) $index)
            );

            array_key_exists('fmt', $nestedAttestationObject) || throw InvalidDataException::create(
                $nestedAttestationObject,
                'Invalid attestation object'
            );
            /** @var string $fmt */
            $fmt = $nestedAttestationObject['fmt'];
            $fmt !== 'compound' || throw InvalidDataException::create(
                $nestedAttestationObject,
                'Compound attestation object in a compound attestation is not allowed.'
            );
            array_key_exists('attStmt', $nestedAttestationObject) || throw InvalidDataException::create(
                $nestedAttestationObject,
                'Invalid attestation object'
            );
            $this->attestationStatementSupportManager->has($fmt) || throw AttestationStatementLoadingException::create(
                $attestation,
                sprintf('Unsupported attestation format "%s" at index %s.', $fmt, (string) $index)
            );

            /** @var array<string, mixed> $nestedAttestationObject */
            $attestationStatementSupport = $this->attestationStatementSupportManager->get($fmt);
            $loadedAttestations[] = $attestationStatementSupport->load($nestedAttestationObject);
        }

        /** @var string $compoundFmt */
        $compoundFmt = $attestation['fmt'];
        $attestationType = $this->deriveAttestationType($loadedAttestations);
        // Create a compound attestation statement with compound trust path
        $attestationStatement = AttestationStatement::create(
            $compoundFmt,
            $loadedAttestations,
            $attestationType,
            EmptyTrustPath::create()
        );

        $this->dispatcher->dispatch(AttestationStatementLoaded::create($attestationStatement));

        return $attestationStatement;
    }

    public function isValid(
        string $clientDataJSONHash,
        AttestationStatement $attestationStatement,
        AuthenticatorData $authenticatorData
    ): bool {
        count($attestationStatement->attStmt) > 1 || throw AttestationStatementVerificationException::create(
            'Compound attestation must contain at least two attestations.'
        );

        // Verify all nested attestations
        $countValid = 0;
        $total = count($attestationStatement->attStmt);

        foreach ($attestationStatement->attStmt as $nestedAttestationObject) {
            /** @var AttestationStatement $nestedAttestationObject */
            $attestationStatementSupport = $this->attestationStatementSupportManager->get(
                $nestedAttestationObject->fmt
            );

            try {
                $isValid = $attestationStatementSupport->isValid(
                    $clientDataJSONHash,
                    $nestedAttestationObject,
                    $authenticatorData
                );

                if ($isValid) {
                    $countValid++;
                }
            } catch (Throwable) {
                // Nothing to do
            }
        }

        if ($this->minimum !== null) {
            return $countValid >= $this->minimum;
        }
        return $countValid / $total >= $this->ratio;
    }

    /**
     * Derives the compound attestation type from the nested attestation types
     * by selecting the weakest (least trusted) type among them.
     *
     * @param array<AttestationStatement> $loadedAttestations
     */
    private function deriveAttestationType(array $loadedAttestations): string
    {
        $weakest = 0;
        foreach ($loadedAttestations as $stmt) {
            $index = array_search($stmt->type, self::ATTESTATION_TYPE_TRUST_ORDER, true);
            if ($index === false) {
                return AttestationStatement::TYPE_NONE;
            }
            if ($index > $weakest) {
                $weakest = $index;
            }
        }

        return self::ATTESTATION_TYPE_TRUST_ORDER[$weakest];
    }

    private function checkRules(?float $ratio, ?int $minimum): void
    {
        if ($ratio !== null && ($ratio <= 0 || $ratio > 1)) {
            throw new InvalidArgumentException('The ratio must be greater than 0 and less than or equal to 1.');
        }

        if ($minimum !== null && $minimum <= 1) {
            throw new InvalidArgumentException('The minimum must be greater than 1.');
        }

        if ($ratio !== null && $minimum !== null) {
            throw new InvalidArgumentException('You cannot define both ratio and minimum at the same time.');
        }

        if ($ratio === null && $minimum === null) {
            throw new InvalidArgumentException('You must define either ratio or minimum.');
        }
    }
}
