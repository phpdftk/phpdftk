<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Multimedia;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Navigator (/Type /Navigator) — ISO 32000-2 §13.2.7.
 *
 * Scripted presentation sequence that drives transitions between pages
 * or slides during multimedia playback.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class Navigator extends PdfObject
{
    public const PDF_TYPE = 'Navigator';

    public ?PdfDictionary $na = null;   // /NA  actions map
    public ?PdfString $nr = null;       // /NR  navigator name
    public ?PdfDictionary $du = null;   // /Duration dict

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->na !== null) {
            $dict->set('NA', $this->na);
        }
        if ($this->nr !== null) {
            $dict->set('NR', $this->nr);
        }
        if ($this->du !== null) {
            $dict->set('Duration', $this->du);
        }
        return $dict->toPdf();
    }
}
