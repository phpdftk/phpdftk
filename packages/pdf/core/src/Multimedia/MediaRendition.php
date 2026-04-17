<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Multimedia;

use ApprLabs\Pdf\Core\PdfReference;

/**
 * Media rendition (/Type /Rendition /S /MR) — ISO 32000-2 §13.2.3.3.
 *
 * Refers to a single MediaClip together with playback and screen params.
 */
class MediaRendition extends Rendition
{
    public MediaClip|PdfReference|null $c = null;               // /C  MediaClip
    public MediaPlayParams|PdfReference|null $p = null;         // /P
    public MediaScreenParams|PdfReference|null $sp = null;      // /SP

    public function getRenditionSubtype(): string
    {
        return 'MR';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->c !== null) {
            $dict->set('C', $this->c);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->sp !== null) {
            $dict->set('SP', $this->sp);
        }
        return $dict->toPdf();
    }
}
