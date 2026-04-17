<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Rich-media execute action (/S /RichMediaExecute) — ISO 32000-2 §13.6.9.
 * Invokes a command on a rich-media annotation's embedded content.
 */
class RichMediaExecuteAction extends Action
{
    public ?PdfReference $ta = null;           // /TA target annotation
    public ?PdfReference $ti = null;           // /TI target instance
    public ?PdfDictionary $cmd = null;         // /CMD command dict

    public function getActionType(): string
    {
        return 'RichMediaExecute';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->ta !== null) {
            $dict->set('TA', $this->ta);
        }
        if ($this->ti !== null) {
            $dict->set('TI', $this->ti);
        }
        if ($this->cmd !== null) {
            $dict->set('CMD', $this->cmd);
        }
        return $dict->toPdf();
    }
}
