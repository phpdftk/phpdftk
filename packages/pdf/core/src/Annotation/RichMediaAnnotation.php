<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * RichMedia annotation (/Subtype /RichMedia).
 */
#[RequiresPdfVersion(PdfVersion::V2_0)]
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
