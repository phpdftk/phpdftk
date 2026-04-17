<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Pattern;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Shading pattern (/Type /Pattern /PatternType 2) — ISO 32000-2 §8.7.3.4.
 *
 * Paints a shading (axial, radial, mesh, etc.) as if it were a color.
 * Required entries: Shading. Optional: Matrix, ExtGState.
 */
class ShadingPattern extends PdfObject
{
    public const PDF_TYPE = 'Pattern';
    public const PATTERN_TYPE = 2;

    public PdfReference|PdfDictionary $shading;    // /Shading
    public ?PdfArray $matrix = null;               // /Matrix
    public ?PdfReference $extGState = null;        // /ExtGState

    public function __construct(PdfReference|PdfDictionary $shading)
    {
        $this->shading = $shading;
    }

    public function getPatternType(): int
    {
        return self::PATTERN_TYPE;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('PatternType', new PdfNumber(self::PATTERN_TYPE));
        $dict->set('Shading', $this->shading);
        if ($this->matrix !== null) {
            $dict->set('Matrix', $this->matrix);
        }
        if ($this->extGState !== null) {
            $dict->set('ExtGState', $this->extGState);
        }
        return $dict->toPdf();
    }
}
