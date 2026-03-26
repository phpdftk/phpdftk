<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfName;

/**
 * PrinterMark annotation (/Subtype /PrinterMark).
 */
class PrinterMarkAnnotation extends Annotation
{
    public ?PdfName $mn = null; // /MN - mark name

    public function getSubtype(): string
    {
        return 'PrinterMark';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->mn !== null) {
            $dict->set('MN', $this->mn);
        }

        return $dict->toPdf();
    }
}
