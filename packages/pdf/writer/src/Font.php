<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Encoding\TextEncoder;
use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\FontParser\TrueTypeData;
use Phpdftk\Pdf\Core\Font\RegisteredFont;

/**
 * Opaque font handle returned by PdfWriter::addFont().
 *
 * Encapsulates the PDF resource name (F1, F2, ...), parsed font data, and
 * the text encoder (when the font uses a single-byte encoding such as
 * WinAnsi). Pass to ContentStream::setFont() to select the font for a
 * subsequent text run; passing the handle (rather than just the resource
 * name) is what makes showText() accept UTF-8 directly.
 */
final class Font implements RegisteredFont
{
    public function __construct(
        private readonly string $resourceName,
        private readonly string $family,
        private readonly TrueTypeData|OpenTypeData|null $parsedData = null,
        private readonly ?TextEncoder $encoder = null,
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

    /**
     * The text encoder that converts UTF-8 input into the byte sequence the
     * underlying PDF font expects. Null for composite/CID fonts, which use
     * the GID-hex path (ContentStream::showTextHex / showUnicodeText).
     */
    public function getTextEncoder(): ?TextEncoder
    {
        return $this->encoder;
    }
}
