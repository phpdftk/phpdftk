<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

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
