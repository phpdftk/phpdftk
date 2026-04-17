<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;

/**
 * Requirement dictionary — ISO 32000-2 §12.10, Table 253.
 *
 * Declares processor capabilities that a conforming reader must support
 * to fully render the document. Referenced from `Catalog::$requirements`.
 */
class Requirement extends PdfObject
{
    public const PDF_TYPE = 'Requirement';

    public PdfName $s;                 // /S - subtype (EnableJavaScripts, …)
    public ?PdfArray $rh = null;       // /RH - requirement handlers

    public function __construct(string $subtype)
    {
        $this->s = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', $this->s);
        if ($this->rh !== null) {
            $dict->set('RH', $this->rh);
        }
        return $dict->toPdf();
    }
}
