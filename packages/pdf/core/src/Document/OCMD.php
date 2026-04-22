<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

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
