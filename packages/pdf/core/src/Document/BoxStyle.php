<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Box-style dictionary — ISO 32000-2 §14.11.2.3, Table 337.
 *
 * Describes how a page crop/bleed/trim/art box guide is drawn in print
 * preview. Carried inline inside a {@see BoxColorInfo} entry.
 */
class BoxStyle implements Serializable
{
    public ?PdfArray $c = null;   // /C  - 3-element RGB color
    public ?float $w = null;      // /W  - line width (default 1)
    public ?PdfName $s = null;    // /S  - style: S, D (dashed)
    public ?PdfArray $d = null;   // /D  - dash pattern

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->c !== null) {
            $dict->set('C', $this->c);
        }
        if ($this->w !== null) {
            $dict->set('W', new PdfNumber($this->w));
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
