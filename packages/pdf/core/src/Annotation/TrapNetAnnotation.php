<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;

/**
 * TrapNet annotation (/Subtype /TrapNet).
 */
class TrapNetAnnotation extends Annotation
{
    public ?PdfString $lastModified = null;  // /LastModified
    public ?PdfNumber $version = null;       // /Version
    public ?PdfArray $annotStates = null;    // /AnnotStates
    public ?PdfArray $fontFauxing = null;    // /FontFauxing

    public function getSubtype(): string
    {
        return 'TrapNet';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->lastModified !== null) {
            $dict->set('LastModified', $this->lastModified);
        }
        if ($this->version !== null) {
            $dict->set('Version', $this->version);
        }
        if ($this->annotStates !== null) {
            $dict->set('AnnotStates', $this->annotStates);
        }
        if ($this->fontFauxing !== null) {
            $dict->set('FontFauxing', $this->fontFauxing);
        }

        return $dict->toPdf();
    }
}
