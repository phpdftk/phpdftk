<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Rendition action (/S /Rendition) — ISO 32000-2 §13.2.2.
 * Controls playback of media renditions.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class RenditionAction extends Action
{
    public ?int $op = null;                  // /OP  operation (0..4)
    public ?PdfReference $r = null;          // /R   rendition
    public ?PdfReference $an = null;         // /AN  screen annotation
    public ?PdfString $js = null;            // /JS  JavaScript

    public function getActionType(): string
    {
        return 'Rendition';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->op !== null) {
            $dict->set('OP', new PdfNumber($this->op));
        }
        if ($this->r !== null) {
            $dict->set('R', $this->r);
        }
        if ($this->an !== null) {
            $dict->set('AN', $this->an);
        }
        if ($this->js !== null) {
            $dict->set('JS', $this->js);
        }
        return $dict->toPdf();
    }
}
