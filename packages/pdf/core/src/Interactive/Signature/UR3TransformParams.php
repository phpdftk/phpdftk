<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;

/**
 * UR3 (usage rights) transform parameters — ISO 32000-2 §12.8.2.3, Table 256.
 *
 * Enables specific reader features (form filling, commenting, etc.) via
 * per-right name arrays.
 */
class UR3TransformParams extends TransformParams
{
    public ?PdfArray $document = null;    // /Document  rights: FullSave
    public ?PdfString $msg = null;        // /Msg       warning shown to user
    public ?PdfName $v = null;            // /V
    public ?PdfArray $annots = null;      // /Annots    rights
    public ?PdfArray $form = null;        // /Form      rights
    public ?PdfArray $signature = null;   // /Signature rights
    public ?PdfArray $ef = null;          // /EF        rights
    public ?PdfString $p = null;          // /P         usage-rights URL

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->document !== null) {
            $dict->set('Document', $this->document);
        }
        if ($this->msg !== null) {
            $dict->set('Msg', $this->msg);
        }
        if ($this->v !== null) {
            $dict->set('V', $this->v);
        }
        if ($this->annots !== null) {
            $dict->set('Annots', $this->annots);
        }
        if ($this->form !== null) {
            $dict->set('Form', $this->form);
        }
        if ($this->signature !== null) {
            $dict->set('Signature', $this->signature);
        }
        if ($this->ef !== null) {
            $dict->set('EF', $this->ef);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        return $dict->toPdf();
    }
}
