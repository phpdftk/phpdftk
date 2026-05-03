<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Rich-media execute action (/S /RichMediaExecute) — ISO 32000-2 §13.6.9.
 * Invokes a command on a rich-media annotation's embedded content.
 */
#[RequiresPdfVersion(PdfVersion::V2_0)]
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
