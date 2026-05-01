<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

/**
 * Marks a PDF object class or property as deprecated in the PDF specification.
 *
 * When {@see $removedIn} is set, strict deprecation mode and ceiling mode
 * will reject the feature at or above that version.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
final class DeprecatedPdfFeature
{
    public readonly ?PdfVersion $removedInVersion;

    public function __construct(
        public readonly string $since,
        public readonly ?string $replacement = null,
        public readonly ?string $removedIn = null,
    ) {
        $this->removedInVersion = $removedIn !== null
            ? PdfVersion::from($removedIn)
            : null;
    }
}
