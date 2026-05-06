<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\XObject;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;

/**
 * Image XObject (/Subtype /Image) — ISO 32000-2 §8.9.5, Table 89.
 */
class ImageXObject extends PdfStream
{
    public const PDF_TYPE    = 'XObject';
    public const PDF_SUBTYPE = 'Image';

    public int $width;                       // /Width - required
    public int $height;                      // /Height - required
    public mixed $colorSpace;                // /ColorSpace
    public int $bitsPerComponent;            // /BitsPerComponent

    /** /Filter may be a single name or an array of names for filter chains. */
    public PdfName|PdfArray|null $filter = null;
    /** /DecodeParms may be a single dict or an array of dicts (one per filter). */
    public PdfDictionary|PdfArray|null $decodeParams = null;

    public ?PdfName $intent = null;          // /Intent
    public ?bool $imageMask = null;          // /ImageMask
    public ?PdfReference $mask = null;       // /Mask
    public ?PdfReference $sMask = null;      // /SMask
    public ?int $sMaskInData = null;         // /SMaskInData
    public ?PdfArray $decode = null;         // /Decode
    public ?bool $interpolate = null;        // /Interpolate
    public ?PdfArray $alternates = null;     // /Alternates
    public ?PdfName $nameField = null;       // /Name (deprecated)
    public ?int $structParent = null;        // /StructParent
    public ?PdfString $id = null;            // /ID
    public ?PdfDictionary $opi = null;       // /OPI
    public ?PdfReference $metadata = null;   // /Metadata - XMP
    public ?PdfReference $oc = null;         // /OC
    public ?PdfArray $af = null;             // /AF
    public ?PdfReference $measure = null;    // /Measure
    public ?PdfReference $ptData = null;     // /PtData
    public ?PdfArray $matte = null;          // /Matte - pre-blended SMask matte

    public function __construct(
        int $width,
        int $height,
        mixed $colorSpace,
        int $bitsPerComponent = 8,
        string $data = '',
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

        if ($this->colorSpace instanceof \Phpdftk\Pdf\Core\Serializable) {
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
        if ($this->sMaskInData !== null) {
            $this->dictionary->set('SMaskInData', new PdfNumber($this->sMaskInData));
        }
        if ($this->decode !== null) {
            $this->dictionary->set('Decode', $this->decode);
        }
        if ($this->interpolate !== null) {
            $this->dictionary->set('Interpolate', new PdfBoolean($this->interpolate));
        }
        if ($this->alternates !== null) {
            $this->dictionary->set('Alternates', $this->alternates);
        }
        if ($this->nameField !== null) {
            $this->dictionary->set('Name', $this->nameField);
        }
        if ($this->structParent !== null) {
            $this->dictionary->set('StructParent', new PdfNumber($this->structParent));
        }
        if ($this->id !== null) {
            $this->dictionary->set('ID', $this->id);
        }
        if ($this->opi !== null) {
            $this->dictionary->set('OPI', $this->opi);
        }
        if ($this->metadata !== null) {
            $this->dictionary->set('Metadata', $this->metadata);
        }
        if ($this->oc !== null) {
            $this->dictionary->set('OC', $this->oc);
        }
        if ($this->af !== null) {
            $this->dictionary->set('AF', $this->af);
        }
        if ($this->measure !== null) {
            $this->dictionary->set('Measure', $this->measure);
        }
        if ($this->ptData !== null) {
            $this->dictionary->set('PtData', $this->ptData);
        }
        if ($this->matte !== null) {
            $this->dictionary->set('Matte', $this->matte);
        }

        return parent::toPdf();
    }
}
