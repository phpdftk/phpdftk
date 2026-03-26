<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfDictionary;

/**
 * RichMedia annotation (/Subtype /RichMedia).
 */
class RichMediaAnnotation extends Annotation
{
    public ?PdfDictionary $richMediaSettings = null; // /RichMediaSettings
    public ?PdfDictionary $richMediaContent = null;  // /RichMediaContent

    public function getSubtype(): string
    {
        return 'RichMedia';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->richMediaSettings !== null) {
            $dict->set('RichMediaSettings', $this->richMediaSettings);
        }
        if ($this->richMediaContent !== null) {
            $dict->set('RichMediaContent', $this->richMediaContent);
        }

        return $dict->toPdf();
    }
}
