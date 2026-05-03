<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Multimedia;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Media screen parameters (/Type /MediaScreenParams) — ISO 32000-2 §13.2.5.
 *
 * Describes where the media plays (window geometry, monitor, background).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class MediaScreenParams extends PdfObject
{
    public const PDF_TYPE = 'MediaScreenParams';

    public ?PdfDictionary $mh = null;   // /MH
    public ?PdfDictionary $be = null;   // /BE

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
        return $dict->toPdf();
    }
}
