<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Filter;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * JPXDecode (JPEG 2000) parameters — ISO 32000-2 §7.4.9.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
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
