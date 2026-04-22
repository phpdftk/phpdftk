<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\ColorSpace;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * CalRGB color space — ISO 32000-2 §8.6.5.3.
 *
 * Serialized as [/CalRGB << /WhitePoint ... /BlackPoint ... /Gamma ... /Matrix ... >>].
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class CalRGB extends ColorSpace
{
    public PdfArray $whitePoint;          // /WhitePoint - required
    public ?PdfArray $blackPoint = null;  // /BlackPoint
    public ?PdfArray $gamma = null;       // /Gamma     - [gR gG gB]
    public ?PdfArray $matrix = null;      // /Matrix    - 9-element

    public function __construct(PdfArray $whitePoint)
    {
        $this->whitePoint = $whitePoint;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('WhitePoint', $this->whitePoint);
        if ($this->blackPoint !== null) {
            $dict->set('BlackPoint', $this->blackPoint);
        }
        if ($this->gamma !== null) {
            $dict->set('Gamma', $this->gamma);
        }
        if ($this->matrix !== null) {
            $dict->set('Matrix', $this->matrix);
        }
        return (new PdfArray([new PdfName('CalRGB'), $dict]))->toPdf();
    }
}
