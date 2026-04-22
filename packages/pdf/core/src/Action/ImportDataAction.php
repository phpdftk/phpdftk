<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\FileSpec\FileSpec;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Import-data action (/S /ImportData) — ISO 32000-2 §12.7.5.4.
 * Imports FDF data from a file into form fields.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class ImportDataAction extends Action
{
    public FileSpec|PdfReference $f;   // /F  required

    public function __construct(FileSpec|PdfReference $f)
    {
        $this->f = $f;
    }

    public function getActionType(): string
    {
        return 'ImportData';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('F', $this->f);
        return $dict->toPdf();
    }
}
