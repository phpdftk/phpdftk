<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\ColorSpace;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * CalGray color space — ISO 32000-2 §8.6.5.2.
 *
 * Serialized as [/CalGray << /WhitePoint ... /BlackPoint ... /Gamma ... >>].
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class CalGray extends ColorSpace
{
    public PdfArray $whitePoint;         // /WhitePoint — required, 3 numbers
    public ?PdfArray $blackPoint = null; // /BlackPoint
    public ?float $gamma = null;         // /Gamma

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
            $dict->set('Gamma', new PdfNumber($this->gamma));
        }
        return (new PdfArray([new PdfName('CalGray'), $dict]))->toPdf();
    }
}
