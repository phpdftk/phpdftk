<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * PrinterMark annotation (/Subtype /PrinterMark).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
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
