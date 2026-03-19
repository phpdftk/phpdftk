<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;

/**
 * JavaScript action (/S /JavaScript).
 * Executes a JavaScript script.
 */
class JavaScriptAction extends Action
{
    public PdfString $js; // /JS - required

    public function __construct(PdfString $js)
    {
        $this->js = $js;
    }

    public function getActionType(): string
    {
        return 'JavaScript';
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getActionType()));
        $dict->set('JS', $this->js);

        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }

        return $dict->toPdf();
    }
}
