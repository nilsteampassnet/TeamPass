<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Webauthn\Exception\MetadataStatementLoadingException;

readonly class ExtensionDescriptor
{
    public function __construct(
        public string $id,
        public ?int $tag,
        public ?string $data,
        #[SerializedName('fail_if_unknown')]
        public bool $failIfUnknown
    ) {
        if ($tag !== null) {
            $tag >= 0 || throw MetadataStatementLoadingException::create(
                'Invalid data. The parameter "tag" shall be a positive integer'
            );
        }
    }

    public static function create(
        string $id,
        ?int $tag = null,
        ?string $data = null,
        bool $failIfUnknown = false
    ): self {
        return new self($id, $tag, $data, $failIfUnknown);
    }
}
