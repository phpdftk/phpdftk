<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Text;

use Phpdftk\Pdf\Core\Font\RegisteredFont;

/**
 * A font an embedding document handed to the SVG text painter through
 * {@see DocumentFontProvider}.
 *
 * Carries both halves the content stream needs:
 *
 *  - `$font` — the PDF-side handle, used for `Tf` (and, for simple
 *    single-byte fonts, the UTF-8 → byte encoder behind `Tj`).
 *  - `$unicodeToGid` — the post-subset Unicode → GID map. Non-empty
 *    only for composite (Type 0 / CID) fonts, which have no
 *    single-byte encoder and must be shown via
 *    `ContentStream::showUnicodeText()`. Empty for the standard-14
 *    Type 1 fonts the built-in {@see FontResolver} picks.
 */
final readonly class DocumentFont
{
    /**
     * @param array<int, int> $unicodeToGid Unicode codepoint → post-subset GID.
     */
    public function __construct(
        public RegisteredFont $font,
        public array $unicodeToGid = [],
    ) {}
}
