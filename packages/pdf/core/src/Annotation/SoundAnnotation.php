<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Sound annotation (/Subtype /Sound).
 */
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
