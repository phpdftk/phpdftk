<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Watermark annotation (/Subtype /Watermark).
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class WatermarkAnnotation extends Annotation
{
    public ?PdfDictionary $fixedPrint = null; // /FixedPrint

    public function getSubtype(): string
    {
        return 'Watermark';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->fixedPrint !== null) {
            $dict->set('FixedPrint', $this->fixedPrint);
        }

        return $dict->toPdf();
    }
}
