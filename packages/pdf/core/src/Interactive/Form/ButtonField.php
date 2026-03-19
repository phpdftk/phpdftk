<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Form;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;

/**
 * Button field (/FT /Btn) - covers push buttons, check boxes, and radio buttons.
 */
class ButtonField extends Field
{
    public ?PdfName $h = null;           // /H - highlight mode
    public ?PdfDictionary $mk = null;    // /MK - appearance characteristics
    public ?PdfArray $opt = null;        // /Opt

    public function __construct()
    {
        $this->ft = new PdfName('Btn');
    }

    public function toPdf(): string
    {
        $dict = $this->buildFieldDictionary();

        if ($this->h !== null) {
            $dict->set('H', $this->h);
        }
        if ($this->mk !== null) {
            $dict->set('MK', $this->mk);
        }
        if ($this->opt !== null) {
            $dict->set('Opt', $this->opt);
        }

        return $dict->toPdf();
    }
}
