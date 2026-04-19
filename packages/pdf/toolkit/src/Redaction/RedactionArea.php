<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Redaction;

/**
 * Defines an area on a page to redact.
 */
final readonly class RedactionArea
{
    public function __construct(
        public int $pageIndex, // 0-based
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {}
}
