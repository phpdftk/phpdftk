<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Hide action (/S /Hide) — ISO 32000-2 §12.6.4.10.
 * Sets the Hidden flag on one or more annotations or form fields.
 */
class HideAction extends Action
{
    /** /T — a single target or an array of targets. */
    public PdfString|PdfArray|PdfReference $t;
    public bool $h = true;   // /H — true = hide, false = show

    public function __construct(PdfString|PdfArray|PdfReference $t, bool $hide = true)
    {
        $this->t = $t;
        $this->h = $hide;
    }

    public function getActionType(): string
    {
        return 'Hide';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('T', $this->t);
        $dict->set('H', new PdfBoolean($this->h));
        return $dict->toPdf();
    }
}
