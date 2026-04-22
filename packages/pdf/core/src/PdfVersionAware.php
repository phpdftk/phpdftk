<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

/**
 * Implemented by objects whose minimum PDF version depends on runtime
 * state (e.g., a property value) rather than a static attribute.
 *
 * The {@see File\VersionRequirementResolver} will call this method and
 * merge its result with any attribute-based requirements.
 */
interface PdfVersionAware
{
    public function getMinimumPdfVersion(): ?PdfVersion;
}
