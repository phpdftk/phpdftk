<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;

/**
 * Caret annotation (/Subtype /Caret).
 */
class CaretAnnotation extends MarkupAnnotation
{
    public ?PdfArray $rd = null;  // /RD - rectangle differences
    public ?PdfName $sy = null;   // /Sy - symbol (None or P)

    public function getSubtype(): string
    {
        return 'Caret';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->rd !== null) {
            $dict->set('RD', $this->rd);
        }
        if ($this->sy !== null) {
            $dict->set('Sy', $this->sy);
        }

        return $dict->toPdf();
    }
}
