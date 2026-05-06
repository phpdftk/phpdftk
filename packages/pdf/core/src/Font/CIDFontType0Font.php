<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

/**
 * CIDFontType0 — CID-keyed font backed by a Type 1 / CFF font program
 * (ISO 32000-2 §9.7.4). Used as the descendant of a /Type0 font when the
 * glyphs come from an OpenType CFF or bare CFF font program.
 */
class CIDFontType0Font extends CIDFont
{
    public function __construct(string $baseFontName, CIDSystemInfo $cidSystemInfo)
    {
        parent::__construct('CIDFontType0', $baseFontName, $cidSystemInfo);
    }
}
