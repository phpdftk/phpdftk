<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Shading;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Abstract base for PDF shading dictionaries — ISO 32000-2 §8.7.4.
 *
 * Common entries: ShadingType, ColorSpace, Background, BBox, AntiAlias.
 * Shading types 1–3 are dictionaries and extend this class directly.
 * Types 4–7 must be streams (see ShadingType4..7).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
abstract class Shading extends PdfObject
{
    public ColorSpace|PdfName|PdfArray $colorSpace;
    public ?PdfArray $background = null;     // /Background
    public ?PdfArray $bbox = null;           // /BBox
    public ?bool $antiAlias = null;          // /AntiAlias

    abstract public function getShadingType(): int;

    protected function baseDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('ShadingType', new PdfNumber($this->getShadingType()));
        $dict->set('ColorSpace', $this->colorSpace);
        if ($this->background !== null) {
            $dict->set('Background', $this->background);
        }
        if ($this->bbox !== null) {
            $dict->set('BBox', $this->bbox);
        }
        if ($this->antiAlias !== null) {
            $dict->set('AntiAlias', new PdfBoolean($this->antiAlias));
        }
        return $dict;
    }
}
