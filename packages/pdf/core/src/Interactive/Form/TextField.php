<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Form;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Text field (/FT /Tx).
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class TextField extends Field
{
    public ?int $maxLen = null;  // /MaxLen

    public function __construct()
    {
        $this->ft = new PdfName('Tx');
    }

    public function toPdf(): string
    {
        $dict = $this->buildFieldDictionary();

        if ($this->maxLen !== null) {
            $dict->set('MaxLen', new PdfNumber($this->maxLen));
        }

        return $dict->toPdf();
    }
}
