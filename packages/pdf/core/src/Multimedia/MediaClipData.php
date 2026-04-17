<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Multimedia;

use ApprLabs\Pdf\Core\FileSpec\FileSpec;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Media clip data (/Type /MediaClip /S /MCD) — ISO 32000-2 §13.2.4.2.
 *
 * Points to the actual media bytes via a FileSpec (external or embedded)
 * plus a MIME type.
 */
class MediaClipData extends MediaClip
{
    public FileSpec|PdfReference $d;               // /D  data (required)
    public ?PdfString $ct = null;                  // /CT content type (MIME)
    public ?PdfDictionary $p = null;               // /P  permissions dict
    public ?PdfString $alt = null;                 // /Alt alternate texts
    public ?PdfDictionary $pl = null;              // /PL player list dict
    public ?PdfDictionary $bu = null;              // /BU base URL

    public function __construct(FileSpec|PdfReference $d)
    {
        $this->d = $d;
    }

    public function getMediaClipSubtype(): string
    {
        return 'MCD';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('D', $this->d);
        if ($this->ct !== null) {
            $dict->set('CT', $this->ct);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->alt !== null) {
            $dict->set('Alt', $this->alt);
        }
        if ($this->pl !== null) {
            $dict->set('PL', $this->pl);
        }
        if ($this->bu !== null) {
            $dict->set('BU', $this->bu);
        }
        return $dict->toPdf();
    }
}
