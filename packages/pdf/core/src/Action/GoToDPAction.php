<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Go-to-document-part action (/S /GoToDP) — ISO 32000-2 §12.6.4.16.
 * Jumps to a document part (PDF 2.0).
 */
#[RequiresPdfVersion(PdfVersion::V2_0)]
class GoToDPAction extends Action
{
    public mixed $d = null;                       // /D  destination
    public PdfDictionary|PdfReference|null $dp = null; // /DP document part

    public function getActionType(): string
    {
        return 'GoToDP';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }
        if ($this->dp !== null) {
            $dict->set('DP', $this->dp);
        }
        return $dict->toPdf();
    }
}
