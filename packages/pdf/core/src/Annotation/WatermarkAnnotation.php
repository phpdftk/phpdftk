<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfDictionary;

/**
 * Watermark annotation (/Subtype /Watermark).
 */
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
