<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Multimedia;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Media play parameters (/Type /MediaPlayParams) — ISO 32000-2 §13.2.5.
 *
 * Describes how a media clip should play (volume, rate, duration, etc.).
 * The spec splits "must-honor" and "best-effort" parameters into two sub
 * dicts (MH and BE) that may carry entries like V (volume), F (fit),
 * PL (playback list), D (duration), A (auto-play), C (controller).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class MediaPlayParams extends PdfObject
{
    public const PDF_TYPE = 'MediaPlayParams';

    public ?PdfDictionary $mh = null;   // /MH must-honor
    public ?PdfDictionary $be = null;   // /BE best-effort
    public ?PdfDictionary $pl = null;   // /PL player-list

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->mh !== null) {
            $dict->set('MH', $this->mh);
        }
        if ($this->be !== null) {
            $dict->set('BE', $this->be);
        }
        if ($this->pl !== null) {
            $dict->set('PL', $this->pl);
        }
        return $dict->toPdf();
    }
}
