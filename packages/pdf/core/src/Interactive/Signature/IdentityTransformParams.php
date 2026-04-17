<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

use ApprLabs\Pdf\Core\PdfName;

/**
 * Identity transform parameters — ISO 32000-2 §12.8.2.
 *
 * The Identity transform covers every byte of the document so that any
 * change invalidates the signature. It carries only the optional /V
 * version entry; there are no other fields.
 */
class IdentityTransformParams extends TransformParams
{
    public ?PdfName $v = null;   // /V

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->v !== null) {
            $dict->set('V', $this->v);
        }
        return $dict->toPdf();
    }
}
