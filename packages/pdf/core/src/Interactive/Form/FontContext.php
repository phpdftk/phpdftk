<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\PdfReference;

/**
 * Carries font metadata needed to render custom (non-standard) fonts
 * in form field appearance streams.
 *
 * When passed to AppearanceGenerator methods, the generator will:
 * - Use hex-encoded glyph IDs instead of literal text strings
 * - Wire the font reference into the FormXObject's /Resources
 *
 * Build via PdfWriter after calling addCompositeFont() / addOpenTypeFont():
 *
 *     $fontCtx = new FontContext(
 *         fontRef:       new PdfReference($type0Font->objectNumber),
 *         unicodeToGid:  $parsedData->fullUnicodeToGid,
 *     );
 */
final class FontContext
{
    /**
     * @param PdfReference         $fontRef      Indirect reference to the registered font object
     * @param array<int, int>      $unicodeToGid Unicode codepoint → glyph ID mapping
     */
    public function __construct(
        public readonly PdfReference $fontRef,
        public readonly array $unicodeToGid,
    ) {}

    /**
     * Convert a UTF-8 string to hex-encoded 2-byte glyph ID sequence.
     */
    public function textToHex(string $text): string
    {
        $hex = '';
        foreach (mb_str_split($text) as $char) {
            $cp = mb_ord($char);
            $gid = $this->unicodeToGid[$cp] ?? 0;
            $hex .= sprintf('%04X', $gid);
        }
        return $hex;
    }
}
