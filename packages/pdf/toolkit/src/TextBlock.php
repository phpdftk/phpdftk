<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

/**
 * A block of text extracted from a PDF page, grouped by font/size.
 */
final class TextBlock
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $fontName = null,
        public readonly ?float $fontSize = null,
    ) {}
}
