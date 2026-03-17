<?php

declare(strict_types=1);

namespace Phpdftk\Action;

use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;

/**
 * Named action (/S /Named).
 * Executes a pre-defined viewer action (e.g., NextPage, PrevPage, FirstPage, LastPage).
 */
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
