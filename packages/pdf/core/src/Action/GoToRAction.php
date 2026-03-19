<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;

/**
 * GoToR action (/S /GoToR).
 *
 * Navigates to a destination in a remote (different) PDF file.
 * The destination (/D) and file (/F) are both required.
 *
 * Example:
 *   $action = new GoToRAction(
 *       new PdfString('/path/to/other.pdf'),
 *       new PdfName('Chapter1')
 *   );
 *   $action->newWindow = true;
 */
class GoToRAction extends Action
{
    public PdfString $f;     // /F - file specification (path or URL)
    public mixed $dest;      // /D - destination (PdfName, PdfArray, or string page index)
    public ?bool $newWindow = null;  // /NewWindow - open in new window

    public function __construct(PdfString $f, mixed $dest)
    {
        $this->f    = $f;
        $this->dest = $dest;
    }

    public function getActionType(): string
    {
        return 'GoToR';
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getActionType()));
        $dict->set('F', $this->f);

        if ($this->dest instanceof \ApprLabs\Pdf\Core\Serializable) {
            $dict->set('D', $this->dest);
        } else {
            $dict->set('D', new \ApprLabs\Pdf\Core\PdfString((string) $this->dest));
        }

        if ($this->newWindow !== null) {
            $dict->set('NewWindow', new PdfBoolean($this->newWindow));
        }
        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }

        return $dict->toPdf();
    }
}
