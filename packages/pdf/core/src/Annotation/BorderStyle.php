<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\Serializable;

/**
 * PDF Border Style dictionary (/Type /Border).
 *
 * Describes the border drawn around annotations that support it
 * (Link, FreeText, Line, Square, Circle, Polygon, PolyLine, etc.).
 * Assigned to the /BS entry of those annotation dictionaries.
 *
 * Style (/S) values:
 *   S - Solid
 *   D - Dashed
 *   B - Beveled
 *   I - Inset
 *   U - Underline
 *
 * Example:
 *   $bs = new BorderStyle();
 *   $bs->w = new PdfNumber(2.0);
 *   $bs->s = new PdfName('D');
 *   $bs->d = new PdfArray([new PdfNumber(3), new PdfNumber(2)]);
 *   $annotation->bs = $bs;
 */
class BorderStyle implements Serializable
{
    public ?PdfNumber $w = null;  // /W - line width (default 1)
    public ?PdfName $s   = null;  // /S - style (default S = Solid)
    /** @var PdfArray|null /D - dash array, e.g. [3 2] */
    public ?PdfArray $d  = null;  // /D - dash pattern (only when S=D)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Border'));

        if ($this->w !== null) {
            $dict->set('W', $this->w);
        }
        if ($this->s !== null) {
            $dict->set('S', $this->s);
        }
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }

        return $dict->toPdf();
    }
}
