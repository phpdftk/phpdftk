<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Interactive form (AcroForm) dictionary.
 * Referenced from the document Catalog's /AcroForm entry.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class AcroForm extends PdfObject
{
    /** @var array<int, PdfReference> */
    public array $fields = [];               // /Fields - required (array of PdfReference)
    public ?bool $needAppearances = null;    // /NeedAppearances
    public ?int $sigFlags = null;            // /SigFlags
    public ?PdfArray $co = null;             // /CO - calculation order
    public ?PdfDictionary $dr = null;        // /DR - default resources
    public ?PdfString $da = null;            // /DA - default appearance
    public ?int $q = null;                   // /Q - justification
    public ?PdfReference $xfa = null;        // /XFA

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Fields', new PdfArray($this->fields));

        if ($this->needAppearances !== null) {
            $dict->set('NeedAppearances', new PdfBoolean($this->needAppearances));
        }
        if ($this->sigFlags !== null) {
            $dict->set('SigFlags', new PdfNumber($this->sigFlags));
        }
        if ($this->co !== null) {
            $dict->set('CO', $this->co);
        }
        if ($this->dr !== null) {
            $dict->set('DR', $this->dr);
        }
        if ($this->da !== null) {
            $dict->set('DA', $this->da);
        }
        if ($this->q !== null) {
            $dict->set('Q', new PdfNumber($this->q));
        }
        if ($this->xfa !== null) {
            $dict->set('XFA', $this->xfa);
        }

        return $dict->toPdf();
    }
}
