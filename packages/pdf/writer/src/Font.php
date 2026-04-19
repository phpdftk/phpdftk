<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer;

use ApprLabs\FontParser\OpenTypeData;
use ApprLabs\FontParser\TrueTypeData;

/**
 * Opaque font handle returned by PdfWriter::addFont().
 *
 * Encapsulates the PDF resource name (F1, F2, ...) and parsed font data
 * so callers never need to know about resource naming or font internals.
 * Pass this to Page::drawText() to select a font.
 */
final class Font
{
    public function __construct(
        private readonly string $resourceName,
        private readonly string $family,
        private readonly TrueTypeData|OpenTypeData|null $parsedData = null,
    ) {}

    public function getFamily(): string
    {
        return $this->family;
    }

    /**
     * @internal Used by Page to emit font operators
     */
    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    /**
     * @internal Used by Page for Unicode text, kerning, ligatures
     */
    public function getParsedData(): TrueTypeData|OpenTypeData|null
    {
        return $this->parsedData;
    }
}
