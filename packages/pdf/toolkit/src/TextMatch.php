<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

/**
 * A single text search match within a PDF.
 */
final class TextMatch
{
    public function __construct(
        /** @var int 1-based page number */
        public readonly int $pageNumber,
        public readonly string $text,
        /** @var int Character offset within the page text */
        public readonly int $offset,
    ) {}
}
