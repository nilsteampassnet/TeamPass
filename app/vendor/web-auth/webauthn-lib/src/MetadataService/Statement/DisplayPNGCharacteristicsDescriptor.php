<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use Webauthn\Exception\MetadataStatementLoadingException;

class DisplayPNGCharacteristicsDescriptor
{
    /**
     * @param RgbPaletteEntry[] $plte
     */
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly int $bitDepth,
        public readonly int $colorType,
        public readonly int $compression,
        public readonly int $filter,
        public readonly int $interlace,
        /**
         * @readonly
         */
        public array $plte = [],
    ) {
        $width >= 0 || throw MetadataStatementLoadingException::create('Invalid width');
        $height >= 0 || throw MetadataStatementLoadingException::create('Invalid height');
        ($bitDepth >= 0 && $bitDepth <= 254) || throw MetadataStatementLoadingException::create('Invalid bit depth');
        ($colorType >= 0 && $colorType <= 254) || throw MetadataStatementLoadingException::create(
            'Invalid color type'
        );
        ($compression >= 0 && $compression <= 254) || throw MetadataStatementLoadingException::create(
            'Invalid compression'
        );
        ($filter >= 0 && $filter <= 254) || throw MetadataStatementLoadingException::create('Invalid filter');
        ($interlace >= 0 && $interlace <= 254) || throw MetadataStatementLoadingException::create(
            'Invalid interlace'
        );
    }

    /**
     * @param RgbPaletteEntry[] $plte
     */
    public static function create(
        int $width,
        int $height,
        int $bitDepth,
        int $colorType,
        int $compression,
        int $filter,
        int $interlace,
        array $plte = []
    ): self {
        return new self($width, $height, $bitDepth, $colorType, $compression, $filter, $interlace, $plte);
    }
}
