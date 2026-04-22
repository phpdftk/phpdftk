<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Multiple Master Type 1 font (/Subtype /MMType1).
 *
 * Multiple Master fonts are a Type 1 extension that allow interpolation
 * across design axes (weight, width, optical size, style). In PDF the
 * BaseFont name encodes the selected instance, with underscores separating
 * axis values and spaces replaced with underscores
 * (ISO 32000-2 §9.6.2.3).
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class MMType1Font extends Font
{
    public function __construct(string $baseFontName)
    {
        $this->subtype = new PdfName('MMType1');
        // Spaces in MM instance names must be encoded as underscores.
        $this->baseFont = new PdfName(str_replace(' ', '_', $baseFontName));
    }
}