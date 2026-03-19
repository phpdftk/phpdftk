<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\Pdf\Core\PdfName;

/**
 * TrueType font (/Subtype /TrueType).
 */
class TrueTypeFont extends Font
{
    public function __construct(string $baseFontName)
    {
        $this->subtype = new PdfName('TrueType');
        $this->baseFont = new PdfName($baseFontName);
    }
}
