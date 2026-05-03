<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;

/**
 * GoTo action (/S /GoTo).
 * Navigates to a destination within the document.
 */
class GoToAction extends Action
{
    public mixed $dest; // /D - destination (PdfName, PdfArray, or string)

    public function __construct(mixed $dest)
    {
        $this->dest = $dest;
    }

    public function getActionType(): string
    {
        return 'GoTo';
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getActionType()));

        if ($this->dest instanceof \Phpdftk\Pdf\Core\Serializable) {
            $dict->set('D', $this->dest);
        } else {
            $dict->set('D', new \Phpdftk\Pdf\Core\PdfString((string) $this->dest));
        }

        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }

        return $dict->toPdf();
    }
}
