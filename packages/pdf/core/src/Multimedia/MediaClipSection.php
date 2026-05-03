<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Multimedia;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Media clip section (/Type /MediaClip /S /MCS) — ISO 32000-2 §13.2.4.3.
 *
 * Defines a temporal slice of another MediaClip.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class MediaClipSection extends MediaClip
{
    public MediaClip|PdfReference $d;              // /D  parent clip
    public ?PdfString $alt = null;                 // /Alt
    public ?PdfDictionary $mh = null;              // /MH
    public ?PdfDictionary $be = null;              // /BE

    public function __construct(MediaClip|PdfReference $d)
    {
        $this->d = $d;
    }

    public function getMediaClipSubtype(): string
    {
        return 'MCS';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('D', $this->d);
        if ($this->alt !== null) {
            $dict->set('Alt', $this->alt);
        }
        if ($this->mh !== null) {
            $dict->set('MH', $this->mh);
        }
        if ($this->be !== null) {
            $dict->set('BE', $this->be);
        }
        return $dict->toPdf();
    }
}
