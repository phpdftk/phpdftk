<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Encoding\WinAnsiTable;
use Phpdftk\FontMetrics\StandardFontMetrics;
use Phpdftk\FontParser\Type1Data;
use Phpdftk\FontParser\Type1Parser;

/**
 * Type 1 font (/Subtype /Type1).
 * Supports the 14 standard PDF fonts as well as custom Type 1 fonts.
 */
class Type1Font extends Font
{
    public ?Type1Data $parsedFontData = null;

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

    /**
     * Create a Type1Font from a PFB or PFA font file.
     *
     * Parses the font program, extracts metrics and encoding, and prepares
     * the font for embedding via PdfWriter.
     */
    public static function fromFile(string $path): self
    {
        $data = (new Type1Parser($path))->parse();

        $font = new self($data->postScriptName, embedWidths: false);
        $font->parsedFontData = $data;
        $font->firstChar = 32;
        $font->lastChar = 255;

        $widthNumbers = [];
        for ($byte = 32; $byte <= 255; $byte++) {
            $widthNumbers[] = new PdfNumber($data->charWidths[$byte] ?? 0);
        }
        $font->widths = new PdfArray($widthNumbers);

        return $font;
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
