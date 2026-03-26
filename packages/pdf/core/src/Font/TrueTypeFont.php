<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\FontParser\TrueTypeData;
use ApprLabs\FontParser\TrueTypeParser;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;

/**
 * TrueType font (/Subtype /TrueType).
 */
class TrueTypeFont extends Font
{
    public ?TrueTypeData $parsedFontData = null;

    public function __construct(string $baseFontName)
    {
        $this->subtype = new PdfName('TrueType');
        $this->baseFont = new PdfName($baseFontName);
    }

    public static function fromFile(string $path): self
    {
        $data = (new TrueTypeParser($path))->parse();

        $font = new self($data->postScriptName);
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
}
