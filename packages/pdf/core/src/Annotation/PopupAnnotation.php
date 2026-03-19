<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Popup annotation (/Subtype /Popup).
 * Displays the pop-up window for a parent annotation's text.
 */
class PopupAnnotation extends Annotation
{
    public ?PdfReference $parent = null; // /Parent
    public ?bool $open = null;           // /Open

    public function getSubtype(): string
    {
        return 'Popup';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->parent !== null) {
            $dict->set('Parent', $this->parent);
        }
        if ($this->open !== null) {
            $dict->set('Open', new PdfBoolean($this->open));
        }

        return $dict->toPdf();
    }
}
