<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Type 3 font (/Subtype /Type3).
 *
 * Glyph procedures are defined as PDF content streams inside the font itself
 * rather than using an external font program. Required fields per ISO 32000-2
 * §9.6.4: FontBBox, FontMatrix, CharProcs, Encoding, FirstChar, LastChar,
 * Widths, and (for tagged PDF) FontDescriptor.
 */
class Type3Font extends Font
{
    public ?PdfArray $fontBBox = null;        // /FontBBox
    public ?PdfArray $fontMatrix = null;      // /FontMatrix

    /** @var array<string, PdfReference> name => content stream reference */
    public array $charProcs = [];             // /CharProcs

    public Resources|PdfDictionary|null $resources = null; // /Resources

    public function __construct(?string $baseFontName = null)
    {
        $this->subtype = new PdfName('Type3');
        if ($baseFontName !== null) {
            $this->baseFont = new PdfName($baseFontName);
        }
        // Standard glyph space — maps 1000 glyph units to 1 text space unit.
        $this->fontMatrix = new PdfArray([
            new PdfNumber(0.001),
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber(0.001),
            new PdfNumber(0),
            new PdfNumber(0),
        ]);
    }

    /**
     * Register a glyph procedure by name.
     */
    public function addCharProc(string $name, PdfReference $stream): void
    {
        $this->charProcs[$name] = $stream;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Subtype', $this->subtype);

        if ($this->baseFont !== null) {
            $dict->set('Name', $this->baseFont);
        }
        if ($this->fontBBox !== null) {
            $dict->set('FontBBox', $this->fontBBox);
        }
        if ($this->fontMatrix !== null) {
            $dict->set('FontMatrix', $this->fontMatrix);
        }
        if (!empty($this->charProcs)) {
            $cp = new PdfDictionary();
            foreach ($this->charProcs as $name => $ref) {
                $cp->set($name, $ref);
            }
            $dict->set('CharProcs', $cp);
        }
        if ($this->encoding !== null) {
            $dict->set('Encoding', $this->encoding);
        }
        if ($this->firstChar !== null) {
            $dict->set('FirstChar', $this->firstChar);
        }
        if ($this->lastChar !== null) {
            $dict->set('LastChar', $this->lastChar);
        }
        if ($this->widths !== null) {
            $dict->set('Widths', $this->widths);
        }
        if ($this->fontDescriptor !== null) {
            $dict->set('FontDescriptor', $this->fontDescriptor);
        }
        if ($this->resources !== null) {
            $dict->set('Resources', $this->resources);
        }
        if ($this->toUnicode !== null) {
            $dict->set('ToUnicode', $this->toUnicode);
        }

        return $dict->toPdf();
    }
}
