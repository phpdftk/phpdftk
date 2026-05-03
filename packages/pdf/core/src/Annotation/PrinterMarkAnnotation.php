<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

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
