<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Filter;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Serializable;

/**
 * JPXDecode (JPEG 2000) parameters — ISO 32000-2 §7.4.9.
 */
class JPXDecodeParams implements Serializable
{
    public ?int $colorTransform = null;  // /ColorTransform
    public ?int $sMaskInData = null;     // /SMaskInData

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->colorTransform !== null) {
            $dict->set('ColorTransform', new PdfNumber($this->colorTransform));
        }
        if ($this->sMaskInData !== null) {
            $dict->set('SMaskInData', new PdfNumber($this->sMaskInData));
        }
        return $dict->toPdf();
    }
}
