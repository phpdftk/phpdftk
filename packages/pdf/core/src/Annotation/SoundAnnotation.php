<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\DeprecatedPdfFeature;

/**
 * Sound annotation (/Subtype /Sound).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
#[DeprecatedPdfFeature(since: '2.0', replacement: 'RichMediaAnnotation', removedIn: '2.0')]
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
