<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfName;

/**
 * Stamp annotation (/Subtype /Stamp).
 */
class StampAnnotation extends Annotation
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
