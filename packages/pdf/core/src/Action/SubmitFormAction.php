<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Submit-form action (/S /SubmitForm) — ISO 32000-2 §12.7.5.2.
 * POSTs form field values to a URL or file.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class SubmitFormAction extends Action
{
    public FileSpec|PdfReference $f;   // /F  URL / file spec
    public ?PdfArray $fields = null;   // /Fields
    public int $flags = 0;             // /Flags

    public function __construct(FileSpec|PdfReference $f)
    {
        $this->f = $f;
    }

    public function getActionType(): string
    {
        return 'SubmitForm';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('F', $this->f);
        if ($this->fields !== null) {
            $dict->set('Fields', $this->fields);
        }
        if ($this->flags !== 0) {
            $dict->set('Flags', new PdfNumber($this->flags));
        }
        return $dict->toPdf();
    }
}
