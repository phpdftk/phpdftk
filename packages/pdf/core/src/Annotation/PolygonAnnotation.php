<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Polygon annotation (/Subtype /Polygon).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class PolygonAnnotation extends MarkupAnnotation
{
    public ?PdfArray $vertices = null;  // /Vertices
    public ?PdfArray $le = null;        // /LE - line ending styles
    public ?PdfArray $ic = null;        // /IC - interior color
    public ?Serializable $be = null;    // /BE - border effect
    public ?PdfName $it = null;         // /IT - intent
    public ?PdfReference $measure = null; // /Measure

    public function getSubtype(): string
    {
        return 'Polygon';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->vertices !== null) {
            $dict->set('Vertices', $this->vertices);
        }
        if ($this->le !== null) {
            $dict->set('LE', $this->le);
        }
        if ($this->ic !== null) {
            $dict->set('IC', $this->ic);
        }
        if ($this->be !== null) {
            $dict->set('BE', $this->be);
        }
        if ($this->it !== null) {
            $dict->set('IT', $this->it);
        }
        if ($this->measure !== null) {
            $dict->set('Measure', $this->measure);
        }

        return $dict->toPdf();
    }
}
