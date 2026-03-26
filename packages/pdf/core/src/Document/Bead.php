<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Article bead dictionary (ISO 32000-2 Table 161).
 *
 * Represents a single bead in an article thread, linking to a specific
 * region on a page and chaining to adjacent beads.
 */
class Bead extends PdfObject
{
    public const PDF_TYPE = 'Bead';

    public ?PdfReference $t = null;  // /T - parent thread
    public ?PdfReference $n = null;  // /N - next bead
    public ?PdfReference $v = null;  // /V - previous bead
    public ?PdfReference $p = null;  // /P - page
    public ?PdfArray $r = null;      // /R - rectangle

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->t !== null) {
            $dict->set('T', $this->t);
        }
        if ($this->n !== null) {
            $dict->set('N', $this->n);
        }
        if ($this->v !== null) {
            $dict->set('V', $this->v);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->r !== null) {
            $dict->set('R', $this->r);
        }

        return $dict->toPdf();
    }
}
