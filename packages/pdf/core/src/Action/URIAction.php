<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;

/**
 * URI action (/S /URI).
 * Opens a URL in the user's browser.
 */
class URIAction extends Action
{
    public PdfString $uri;        // /URI - required
    public ?bool $isMap = null;   // /IsMap

    public function __construct(PdfString $uri)
    {
        $this->uri = $uri;
    }

    public function getActionType(): string
    {
        return 'URI';
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getActionType()));
        $dict->set('URI', $this->uri);

        if ($this->isMap !== null) {
            $dict->set('IsMap', new PdfBoolean($this->isMap));
        }
        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }

        return $dict->toPdf();
    }
}
