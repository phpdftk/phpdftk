<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;

/**
 * Linearization parameter dictionary — ISO 32000-2 §F.2.1.
 *
 * First object in a linearized (web-optimized) PDF file. Describes the
 * layout parameters needed by a reader to fetch the document's first
 * page and catalog without downloading the entire file.
 *
 * This class is object-model only; `PdfWriter` does not yet emit
 * linearized files.
 */
class LinearizationParameters extends PdfObject
{
    public float $linearized = 1.0;       // /Linearized - version (always 1)
    public int $l = 0;                    // /L - file length
    public PdfArray $h;                   // /H - hint stream offsets [offset length ...]
    public int $o = 0;                    // /O - first page object number
    public int $e = 0;                    // /E - offset of first page end
    public int $n = 0;                    // /N - number of pages
    public int $t = 0;                    // /T - offset of first xref entry

    public function __construct()
    {
        $this->h = new PdfArray([]);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Linearized', new PdfNumber($this->linearized));
        $dict->set('L', new PdfNumber($this->l));
        $dict->set('H', $this->h);
        $dict->set('O', new PdfNumber($this->o));
        $dict->set('E', new PdfNumber($this->e));
        $dict->set('N', new PdfNumber($this->n));
        $dict->set('T', new PdfNumber($this->t));
        return $dict->toPdf();
    }
}
