<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;

/**
 * Ink annotation (/Subtype /Ink).
 * Represents a freehand "scribble" on the page.
 */
class InkAnnotation extends Annotation
{
    public PdfArray $inkList;            // /InkList - required

    public function __construct(PdfArray $rect, PdfArray $inkList)
    {
        parent::__construct($rect);
        $this->inkList = $inkList;
    }

    public function getSubtype(): string
    {
        return 'Ink';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();
        $dict->set('InkList', $this->inkList);

        return $dict->toPdf();
    }
}
