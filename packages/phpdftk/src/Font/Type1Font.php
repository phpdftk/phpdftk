<?php

declare(strict_types=1);

namespace Phpdftk\Font;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Encoding\WinAnsiTable;
use Phpdftk\FontMetrics\StandardFontMetrics;

/**
 * Type 1 font (/Subtype /Type1).
 * Supports the 14 standard PDF fonts as well as custom Type 1 fonts.
 */
class Type1Font extends Font
{
    public function __construct(StandardFont|string $font, bool $embedWidths = true)
    {
        $this->subtype = new PdfName('Type1');
        $name = $font instanceof StandardFont ? $font->value : $font;
        $this->baseFont = new PdfName($name);

        // Embed character widths for standard fonts when requested
        if ($embedWidths && $font instanceof StandardFont) {
            $this->populateWidths($font->value);
        }
    }

    private function populateWidths(string $postScriptName): void
    {
        try {
            $afm  = StandardFontMetrics::get($postScriptName);
            $encoding = WinAnsiTable::getTable();

            // FirstChar and LastChar for WinAnsi are typically 32–255
            $firstChar = 32;
            $lastChar  = 255;

            $widthItems = [];
            for ($byte = $firstChar; $byte <= $lastChar; $byte++) {
                $glyphName = $encoding[$byte] ?? '.notdef';
                $widthItems[] = new PdfNumber($afm->getWidth($glyphName));
            }

            $this->firstChar = $firstChar;
            $this->lastChar  = $lastChar;
            $this->widths    = new PdfArray($widthItems);
        } catch (\InvalidArgumentException) {
            // Unknown font — skip width embedding
        }
    }
}
