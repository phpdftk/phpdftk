<?php

declare(strict_types=1);

namespace Phpdftk\Annotation;

use Phpdftk\Core\PdfArray;

/**
 * Highlight annotation (/Subtype /Highlight).
 */
class HighlightAnnotation extends Annotation
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
