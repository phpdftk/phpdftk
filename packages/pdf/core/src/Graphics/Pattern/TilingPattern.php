<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Pattern;

use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Tiling pattern (/Type /Pattern /PatternType 1) — ISO 32000-2 §8.7.3.
 *
 * A stream whose body is a PDF content stream painting one cell of the
 * pattern. The cell is tiled across the target area using XStep/YStep.
 *
 * Required entries: PaintType, TilingType, BBox, XStep, YStep, Resources.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class TilingPattern extends PdfStream
{
    public const PDF_TYPE = 'Pattern';
    public const PATTERN_TYPE = 1;

    public int $paintType;      // /PaintType  1 = colored, 2 = uncolored
    public int $tilingType;     // /TilingType 1..3
    public PdfArray $bbox;      // /BBox
    public float $xStep;        // /XStep
    public float $yStep;        // /YStep
    public Resources|PdfDictionary $resources;   // /Resources
    public ?PdfArray $matrix = null;             // /Matrix

    public function __construct(
        int $paintType,
        int $tilingType,
        PdfArray $bbox,
        float $xStep,
        float $yStep,
        Resources|PdfDictionary $resources,
        string $contentStream = ''
    ) {
        parent::__construct(new PdfDictionary(), $contentStream);
        $this->paintType = $paintType;
        $this->tilingType = $tilingType;
        $this->bbox = $bbox;
        $this->xStep = $xStep;
        $this->yStep = $yStep;
        $this->resources = $resources;
    }

    public function getPatternType(): int
    {
        return self::PATTERN_TYPE;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('PatternType', new PdfNumber(self::PATTERN_TYPE));
        $this->dictionary->set('PaintType', new PdfNumber($this->paintType));
        $this->dictionary->set('TilingType', new PdfNumber($this->tilingType));
        $this->dictionary->set('BBox', $this->bbox);
        $this->dictionary->set('XStep', new PdfNumber($this->xStep));
        $this->dictionary->set('YStep', new PdfNumber($this->yStep));
        $this->dictionary->set('Resources', $this->resources);
        if ($this->matrix !== null) {
            $this->dictionary->set('Matrix', $this->matrix);
        }
        return parent::toPdf();
    }
}
