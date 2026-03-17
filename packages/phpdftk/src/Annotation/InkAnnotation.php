<?php

declare(strict_types=1);

namespace Phpdftk\Annotation;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfDictionary;

/**
 * Ink annotation (/Subtype /Ink).
 * Represents a freehand "scribble" on the page.
 */
class InkAnnotation extends Annotation
{
    public PdfArray $inkList;            // /InkList - required
    public ?PdfDictionary $bs = null;    // /BS - border style

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

        if ($this->bs !== null) {
            $dict->set('BS', $this->bs);
        }

        return $dict->toPdf();
    }
}
