<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

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
