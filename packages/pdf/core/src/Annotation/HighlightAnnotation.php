<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Highlight annotation (/Subtype /Highlight).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class HighlightAnnotation extends MarkupAnnotation
{
    public PdfArray $quadPoints; // /QuadPoints - required

    public function __construct(PdfArray $rect, PdfArray $quadPoints)
    {
        parent::__construct($rect);
        $this->quadPoints = $quadPoints;
    }

    public function getSubtype(): string
    {
        return 'Highlight';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();
        $dict->set('QuadPoints', $this->quadPoints);

        return $dict->toPdf();
    }
}
