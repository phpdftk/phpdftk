<?php

declare(strict_types=1);

namespace Phpdftk\Encoding;

/**
 * Converts a UTF-8 string into the byte sequence expected by a PDF font's
 * encoding (e.g. WinAnsi).
 *
 * Implementations are stateful: codepoints that have no representation in
 * the target encoding accumulate in an internal list so callers can surface
 * a single batched diagnostic instead of throwing on every showText.
 */
interface TextEncoder
{
    /**
     * Encode UTF-8 input to the target encoding. Unmappable codepoints are
     * substituted with 0x3F ('?') and recorded for later inspection.
     */
    public function encode(string $utf8): string;

    /**
     * Codepoints encountered since construction that could not be mapped.
     * Returned in encounter order, with duplicates preserved so callers can
     * see how often a missing glyph was requested.
     *
     * @return list<int>
     */
    public function getMissingCodepoints(): array;
}
