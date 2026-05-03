<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * JavaScript action (/S /JavaScript).
 * Executes a JavaScript script.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
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
