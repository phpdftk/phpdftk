<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Form;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;

/**
 * Signature field (/FT /Sig).
 */
class SignatureField extends Field
{
    public ?int $sigFlags = null; // /SigFlags

    public function __construct()
    {
        $this->ft = new PdfName('Sig');
    }

    public function toPdf(): string
    {
        $dict = $this->buildFieldDictionary();

        if ($this->sigFlags !== null) {
            $dict->set('SigFlags', new PdfNumber($this->sigFlags));
        }

        return $dict->toPdf();
    }
}
