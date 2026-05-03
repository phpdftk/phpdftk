<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Multimedia;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Media criteria dictionary (/Type /MediaCriteria) —
 * ISO 32000-2 §13.2.3.6.
 *
 * Inside a rendition's /MH (must-honor) or /BE (best-effort) dictionary
 * to restrict when a rendition is allowed to play: audio support,
 * closed caption presence, locale, bit depth, screen size, etc.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class MediaCriteria extends PdfObject
{
    public const PDF_TYPE = 'MediaCriteria';

    public ?bool $a = null;              // /A audio
    public ?bool $c = null;              // /C captions
    public ?bool $o = null;              // /O overdub
    public ?bool $s = null;              // /S subtitles
    public ?int $r = null;               // /R bit rate (kbps)
    public ?PdfDictionary $d = null;     // /D min bit depth (MinBitDepth)
    public ?PdfDictionary $z = null;     // /Z min screen size (MinScreenSize)
    public ?PdfArray $v = null;          // /V version array
    public ?PdfArray $p = null;          // /P permissions array
    public ?PdfArray $l = null;          // /L languages array

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->a !== null) {
            $dict->set('A', new PdfBoolean($this->a));
        }
        if ($this->c !== null) {
            $dict->set('C', new PdfBoolean($this->c));
        }
        if ($this->o !== null) {
            $dict->set('O', new PdfBoolean($this->o));
        }
        if ($this->s !== null) {
            $dict->set('S', new PdfBoolean($this->s));
        }
        if ($this->r !== null) {
            $dict->set('R', new PdfNumber($this->r));
        }
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }
        if ($this->z !== null) {
            $dict->set('Z', $this->z);
        }
        if ($this->v !== null) {
            $dict->set('V', $this->v);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->l !== null) {
            $dict->set('L', $this->l);
        }
        return $dict->toPdf();
    }
}
