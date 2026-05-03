<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Named action (/S /Named).
 * Executes a pre-defined viewer action (e.g., NextPage, PrevPage, FirstPage, LastPage).
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class NamedAction extends Action
{
    public PdfName $n; // /N - named action

    public function __construct(PdfName $n)
    {
        $this->n = $n;
    }

    public function getActionType(): string
    {
        return 'Named';
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getActionType()));
        $dict->set('N', $this->n);

        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }

        return $dict->toPdf();
    }
}
