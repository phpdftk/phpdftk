<?php

declare(strict_types=1);

namespace Phpdftk\Interactive\Form;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;

/**
 * Choice field (/FT /Ch) - covers list boxes and combo boxes.
 */
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
