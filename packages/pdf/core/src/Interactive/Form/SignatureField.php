<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Signature field (/FT /Sig) — ISO 32000-2 §12.7.5.5.
 *
 * The field's /V may be a typed {@see SignatureValue}, an inline
 * PdfDictionary, or a reference to a SignatureValue indirect object.
 * /Lock and /SV provide an optional lock dict and seed-value dict.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class SignatureField extends Field
{
    public ?int $sigFlags = null;                                          // /SigFlags  (carried on AcroForm)
    public SigFieldLock|PdfDictionary|PdfReference|null $lock = null;      // /Lock
    public SeedValueDictionary|PdfDictionary|PdfReference|null $sv = null; // /SV seed-value

    public function __construct()
    {
        $this->ft = new PdfName('Sig');
    }

    /**
     * Attach a SignatureValue directly as the field value.
     */
    public function setSignatureValue(SignatureValue|PdfReference $value): void
    {
        $this->v = $value;
    }

    public function toPdf(): string
    {
        $dict = $this->buildFieldDictionary();

        if ($this->sigFlags !== null) {
            $dict->set('SigFlags', new PdfNumber($this->sigFlags));
        }
        if ($this->lock !== null) {
            $dict->set('Lock', $this->lock);
        }
        if ($this->sv !== null) {
            $dict->set('SV', $this->sv);
        }

        return $dict->toPdf();
    }
}
