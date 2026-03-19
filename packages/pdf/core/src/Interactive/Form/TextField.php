<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Form;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;

/**
 * Text field (/FT /Tx).
 */
class TextField extends Field
{
    public ?int $maxLen = null;  // /MaxLen
    public ?int $q = null;       // /Q - justification (0=left, 1=center, 2=right)

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
        if ($this->q !== null) {
            $dict->set('Q', new PdfNumber($this->q));
        }

        return $dict->toPdf();
    }
}
