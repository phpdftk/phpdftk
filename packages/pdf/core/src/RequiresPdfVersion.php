<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core;

/**
 * Declares the minimum PDF version required by a class or property.
 *
 * Applied at class level for features entirely new in a given version,
 * or at property level for fields added to an existing class in a later version.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
final class RequiresPdfVersion
{
    public function __construct(
        public readonly PdfVersion $minimumVersion,
        public readonly ?string $reason = null,
    ) {}
}
