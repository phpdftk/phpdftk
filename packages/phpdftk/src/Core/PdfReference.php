<?php

declare(strict_types=1);

namespace Phpdftk\Core;

/**
 * Represents an indirect reference to another PDF object: X 0 R
 */
class PdfReference implements Serializable
{
    public function __construct(
        public readonly int $objectNumber,
        public readonly int $generationNumber = 0
    ) {
    }

    public function toPdf(): string
    {
        return sprintf('%d %d R', $this->objectNumber, $this->generationNumber);
    }
}
