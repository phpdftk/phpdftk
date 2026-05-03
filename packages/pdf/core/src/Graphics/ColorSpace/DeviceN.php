<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\ColorSpace;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * DeviceN color space — ISO 32000-2 §8.6.6.5.
 *
 * Serialized as [/DeviceN <names> <alternateSpace> <tintTransform> <attributes>].
 * `attributes` is an optional dictionary carrying /Subtype, /Colorants,
 * /Process, /MixingHints.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class DeviceN extends ColorSpace
{
    public PdfArray $names;                               // colorant names array
    public ColorSpace|PdfName|PdfArray $alternateSpace;
    public PdfReference $tintTransform;                   // function reference
    public ?PdfDictionary $attributes = null;

    public function __construct(
        PdfArray $names,
        ColorSpace|PdfName|PdfArray $alternateSpace,
        PdfReference $tintTransform
    ) {
        $this->names = $names;
        $this->alternateSpace = $alternateSpace;
        $this->tintTransform = $tintTransform;
    }

    public function toPdf(): string
    {
        $items = [
            new PdfName('DeviceN'),
            $this->names,
            $this->alternateSpace,
            $this->tintTransform,
        ];
        if ($this->attributes !== null) {
            $items[] = $this->attributes;
        }
        return (new PdfArray($items))->toPdf();
    }
}
