<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Button field (/FT /Btn) - covers push buttons, check boxes, and radio buttons.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
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
