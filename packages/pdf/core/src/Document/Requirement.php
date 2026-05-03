<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Requirement dictionary — ISO 32000-2 §12.10, Table 253.
 *
 * Declares processor capabilities that a conforming reader must support
 * to fully render the document. Referenced from `Catalog::$requirements`.
 */
#[RequiresPdfVersion(PdfVersion::V1_7)]
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
