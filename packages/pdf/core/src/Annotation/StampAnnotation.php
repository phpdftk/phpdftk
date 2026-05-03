<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Stamp annotation (/Subtype /Stamp).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class StampAnnotation extends MarkupAnnotation
{
    public ?PdfName $name = null; // /Name - stamp type

    public function getSubtype(): string
    {
        return 'Stamp';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->name !== null) {
            $dict->set('Name', $this->name);
        }

        return $dict->toPdf();
    }
}
