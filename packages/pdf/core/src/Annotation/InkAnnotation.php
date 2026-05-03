<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Ink annotation (/Subtype /Ink).
 * Represents a freehand "scribble" on the page.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class InkAnnotation extends MarkupAnnotation
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
