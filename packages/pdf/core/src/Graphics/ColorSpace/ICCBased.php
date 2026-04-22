<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\ColorSpace;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * ICCBased color space — ISO 32000-2 §8.6.5.5.
 *
 * Serialized as [/ICCBased <iccProfileStream 0 R>]. The profile stream
 * dictionary carries /N, /Alternate, /Range, and /Metadata.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class ICCBased extends ColorSpace
{
    public PdfReference $profile;   // reference to the ICC profile stream

    public function __construct(PdfReference $profile)
    {
        $this->profile = $profile;
    }

    public function toPdf(): string
    {
        return (new PdfArray([new PdfName('ICCBased'), $this->profile]))->toPdf();
    }
}
