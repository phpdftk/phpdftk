<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;
use ApprLabs\Pdf\Core\DeprecatedPdfFeature;

/**
 * Sound annotation (/Subtype /Sound).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
#[DeprecatedPdfFeature(since: '2.0', replacement: 'RichMediaAnnotation')]
class SoundAnnotation extends MarkupAnnotation
{
    public ?PdfReference $sound = null; // /Sound - sound object
    public ?PdfName $name = null;       // /Name - icon name

    public function getSubtype(): string
    {
        return 'Sound';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->sound !== null) {
            $dict->set('Sound', $this->sound);
        }
        if ($this->name !== null) {
            $dict->set('Name', $this->name);
        }

        return $dict->toPdf();
    }
}
