<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Square annotation (/Subtype /Square).
 */
class SquareAnnotation extends MarkupAnnotation
{
    public ?PdfArray $ic = null;       // /IC - interior color
    public ?Serializable $be = null;   // /BE - border effect
    public ?PdfArray $rd = null;       // /RD - rectangle differences
    public ?PdfReference $measure = null; // /Measure

    public function getSubtype(): string
    {
        return 'Square';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->ic !== null) {
            $dict->set('IC', $this->ic);
        }
        if ($this->be !== null) {
            $dict->set('BE', $this->be);
        }
        if ($this->rd !== null) {
            $dict->set('RD', $this->rd);
        }
        if ($this->measure !== null) {
            $dict->set('Measure', $this->measure);
        }

        return $dict->toPdf();
    }
}
