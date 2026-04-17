<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Multimedia;

use ApprLabs\Pdf\Core\PdfArray;

/**
 * Selector rendition (/Type /Rendition /S /SR) — ISO 32000-2 §13.2.3.4.
 *
 * Wraps an ordered array of candidate renditions; the viewer picks the
 * first playable one.
 */
class SelectorRendition extends Rendition
{
    public ?PdfArray $r = null;   // /R  array of Rendition references

    public function getRenditionSubtype(): string
    {
        return 'SR';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->r !== null) {
            $dict->set('R', $this->r);
        }
        return $dict->toPdf();
    }
}
