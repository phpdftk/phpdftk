<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

/**
 * Marks a PDF object class or property as deprecated in the PDF specification.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
final class DeprecatedPdfFeature
{
    public function __construct(
        public readonly string $since,
        public readonly ?string $replacement = null,
    ) {}
}
