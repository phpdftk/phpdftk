<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;

/**
 * Highlight annotation (/Subtype /Highlight).
 */
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
