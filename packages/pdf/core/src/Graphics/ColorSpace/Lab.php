<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\ColorSpace;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * L*a*b* color space — ISO 32000-2 §8.6.5.4.
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class Lab extends ColorSpace
{
    public PdfArray $whitePoint;          // /WhitePoint - required
    public ?PdfArray $blackPoint = null;  // /BlackPoint
    public ?PdfArray $range = null;       // /Range      - [a_min a_max b_min b_max]

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
        if ($this->range !== null) {
            $dict->set('Range', $this->range);
        }
        return (new PdfArray([new PdfName('Lab'), $dict]))->toPdf();
    }
}
