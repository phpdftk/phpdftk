<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Circle annotation (/Subtype /Circle).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class CircleAnnotation extends MarkupAnnotation
{
    public ?PdfArray $ic = null;       // /IC - interior color
    public ?Serializable $be = null;   // /BE - border effect
    public ?PdfArray $rd = null;       // /RD - rectangle differences
    public ?PdfReference $measure = null; // /Measure

    public function getSubtype(): string
    {
        return 'Circle';
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
