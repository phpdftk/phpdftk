<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Filter;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Serializable;

/**
 * DCTDecode (JPEG) parameters — ISO 32000-2 §7.4.8, Table 13.
 */
class DCTDecodeParams implements Serializable
{
    public ?int $colorTransform = null;  // /ColorTransform

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->colorTransform !== null) {
            $dict->set('ColorTransform', new PdfNumber($this->colorTransform));
        }
        return $dict->toPdf();
    }
}
