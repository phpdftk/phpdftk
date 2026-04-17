<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\Document\TransitionDict;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Transition action (/S /Trans) — ISO 32000-2 §12.6.4.14.
 * Controls the visual transition when jumping to a destination.
 */
class TransAction extends Action
{
    public TransitionDict|PdfReference $trans;   // /Trans

    public function __construct(TransitionDict|PdfReference $trans)
    {
        $this->trans = $trans;
    }

    public function getActionType(): string
    {
        return 'Trans';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('Trans', $this->trans);
        return $dict->toPdf();
    }
}
