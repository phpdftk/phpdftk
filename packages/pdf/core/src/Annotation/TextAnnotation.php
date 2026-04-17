<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Text annotation (/Subtype /Text) - a "sticky note" annotation.
 */
class TextAnnotation extends MarkupAnnotation
{
    public ?bool $open = null;              // /Open
    public ?PdfName $name = null;           // /Name (icon: Note, Comment, Key, etc.)
    public ?PdfString $state = null;        // /State
    public ?PdfString $stateModel = null;   // /StateModel

    public function getSubtype(): string
    {
        return 'Text';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->open !== null) {
            $dict->set('Open', new PdfBoolean($this->open));
        }
        if ($this->name !== null) {
            $dict->set('Name', $this->name);
        }
        if ($this->state !== null) {
            $dict->set('State', $this->state);
        }
        if ($this->stateModel !== null) {
            $dict->set('StateModel', $this->stateModel);
        }

        return $dict->toPdf();
    }
}
