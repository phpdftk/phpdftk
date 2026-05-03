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
 * PDF Optional Content Membership Dictionary (ISO 32000-2 Table 97).
 *
 * Determines visibility based on the state of one or more OCGs.
 *
 * Example:
 *   $ocmd = new OCMD();
 *   $ocmd->ocgs = new PdfArray([new PdfReference($ocg->objectNumber)]);
 *   $ocmd->p = new PdfName('AnyOn');
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class OCMD extends PdfObject
{
    public const PDF_TYPE = 'OCMD';

    public ?PdfArray $ocgs = null;  // /OCGs - array of OCG refs
    public ?PdfName $p = null;      // /P - visibility policy (AllOn, AnyOn, AnyOff, AllOff)
    public ?PdfArray $ve = null;    // /VE - visibility expression

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->ocgs !== null) {
            $dict->set('OCGs', $this->ocgs);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->ve !== null) {
            $dict->set('VE', $this->ve);
        }

        return $dict->toPdf();
    }
}
