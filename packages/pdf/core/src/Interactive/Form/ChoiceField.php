<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Form;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Choice field (/FT /Ch) - covers list boxes and combo boxes.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class ChoiceField extends Field
{
    public ?PdfArray $opt = null;  // /Opt - options
    public ?int $ti = null;        // /TI - top index
    public ?PdfArray $i = null;    // /I - selected indices

    public function __construct()
    {
        $this->ft = new PdfName('Ch');
    }

    public function toPdf(): string
    {
        $dict = $this->buildFieldDictionary();

        if ($this->opt !== null) {
            $dict->set('Opt', $this->opt);
        }
        if ($this->ti !== null) {
            $dict->set('TI', new PdfNumber($this->ti));
        }
        if ($this->i !== null) {
            $dict->set('I', $this->i);
        }

        return $dict->toPdf();
    }
}
