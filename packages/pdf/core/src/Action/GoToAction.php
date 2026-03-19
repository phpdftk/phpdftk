<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;

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

        if ($this->dest instanceof \ApprLabs\Pdf\Core\Serializable) {
            $dict->set('D', $this->dest);
        } else {
            $dict->set('D', new \ApprLabs\Pdf\Core\PdfString((string) $this->dest));
        }

        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }

        return $dict->toPdf();
    }
}
