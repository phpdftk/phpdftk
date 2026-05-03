<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Popup annotation (/Subtype /Popup).
 * Displays the pop-up window for a parent annotation's text.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
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
