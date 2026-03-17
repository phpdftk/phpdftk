<?php

declare(strict_types=1);

namespace Phpdftk\Graphics\XObject;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfBoolean;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfStream;

/**
 * Image XObject (/Subtype /Image).
 * Encapsulates a raster image embedded in a PDF stream.
 */
class ImageXObject extends PdfStream
{
    public const PDF_TYPE    = 'XObject';
    public const PDF_SUBTYPE = 'Image';

    public int $width;                       // /Width - required
    public int $height;                      // /Height - required
    public mixed $colorSpace;                // /ColorSpace - required
    public int $bitsPerComponent;            // /BitsPerComponent
    public ?PdfName $filter = null;          // /Filter
    public ?PdfArray $decodeParams = null;   // /DecodeParms
    public ?PdfReference $intent = null;     // /Intent
    public ?bool $imageMask = null;          // /ImageMask
    public ?PdfReference $mask = null;       // /Mask
    public ?PdfReference $sMask = null;      // /SMask
    public ?bool $interpolate = null;        // /Interpolate
    public ?PdfArray $alternates = null;     // /Alternates

    public function __construct(
        int $width,
        int $height,
        mixed $colorSpace,
        int $bitsPerComponent = 8,
        string $data = ''
    ) {
        parent::__construct(new PdfDictionary(), $data);
        $this->width = $width;
        $this->height = $height;
        $this->colorSpace = $colorSpace;
        $this->bitsPerComponent = $bitsPerComponent;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('Subtype', new PdfName(self::PDF_SUBTYPE));
        $this->dictionary->set('Width', new PdfNumber($this->width));
        $this->dictionary->set('Height', new PdfNumber($this->height));

        if ($this->colorSpace instanceof \Phpdftk\Core\Serializable) {
            $this->dictionary->set('ColorSpace', $this->colorSpace);
        } else {
            $this->dictionary->set('ColorSpace', new PdfName((string) $this->colorSpace));
        }

        $this->dictionary->set('BitsPerComponent', new PdfNumber($this->bitsPerComponent));

        if ($this->filter !== null) {
            $this->dictionary->set('Filter', $this->filter);
        }
        if ($this->decodeParams !== null) {
            $this->dictionary->set('DecodeParms', $this->decodeParams);
        }
        if ($this->intent !== null) {
            $this->dictionary->set('Intent', $this->intent);
        }
        if ($this->imageMask !== null) {
            $this->dictionary->set('ImageMask', new PdfBoolean($this->imageMask));
        }
        if ($this->mask !== null) {
            $this->dictionary->set('Mask', $this->mask);
        }
        if ($this->sMask !== null) {
            $this->dictionary->set('SMask', $this->sMask);
        }
        if ($this->interpolate !== null) {
            $this->dictionary->set('Interpolate', new PdfBoolean($this->interpolate));
        }
        if ($this->alternates !== null) {
            $this->dictionary->set('Alternates', $this->alternates);
        }

        return parent::toPdf();
    }
}
