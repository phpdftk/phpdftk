<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Shading;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;

/**
 * Abstract base for stream-based shading types 4–7.
 *
 * All mesh-based shadings share: BitsPerCoordinate, BitsPerComponent,
 * BitsPerFlag (types 4, 6, 7 only), Decode, Function (optional).
 * Subclasses contribute their own required entries.
 */
abstract class MeshShading extends PdfStream
{
    public ColorSpace|PdfName|PdfArray $colorSpace;
    public ?PdfArray $background = null;
    public ?PdfArray $bbox = null;
    public ?bool $antiAlias = null;

    public int $bitsPerCoordinate;
    public int $bitsPerComponent;
    public PdfArray $decode;
    public PdfReference|PdfArray|null $function = null;

    public function __construct(
        ColorSpace|PdfName|PdfArray $colorSpace,
        int $bitsPerCoordinate,
        int $bitsPerComponent,
        PdfArray $decode,
        string $meshData = ''
    ) {
        parent::__construct(new PdfDictionary(), $meshData);
        $this->colorSpace = $colorSpace;
        $this->bitsPerCoordinate = $bitsPerCoordinate;
        $this->bitsPerComponent = $bitsPerComponent;
        $this->decode = $decode;
    }

    abstract public function getShadingType(): int;

    protected function populateCommon(): void
    {
        $this->dictionary->set('ShadingType', new PdfNumber($this->getShadingType()));
        $this->dictionary->set('ColorSpace', $this->colorSpace);
        if ($this->background !== null) {
            $this->dictionary->set('Background', $this->background);
        }
        if ($this->bbox !== null) {
            $this->dictionary->set('BBox', $this->bbox);
        }
        if ($this->antiAlias !== null) {
            $this->dictionary->set('AntiAlias', new PdfBoolean($this->antiAlias));
        }
        $this->dictionary->set('BitsPerCoordinate', new PdfNumber($this->bitsPerCoordinate));
        $this->dictionary->set('BitsPerComponent', new PdfNumber($this->bitsPerComponent));
        $this->dictionary->set('Decode', $this->decode);
        if ($this->function !== null) {
            $this->dictionary->set('Function', $this->function);
        }
    }
}
