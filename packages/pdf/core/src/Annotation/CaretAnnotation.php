<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Caret annotation (/Subtype /Caret).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
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
