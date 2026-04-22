<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Border Effect dictionary (/BE).
 *
 * Describes a visual effect to apply to the border of an annotation
 * (FreeText, Square, Circle, Polygon, PolyLine).
 *
 * Style (/S) values:
 *   S - No effect (default)
 *   C - Cloudy border
 *
 * Example:
 *   $be = new BorderEffect();
 *   $be->s = new PdfName('C');
 *   $be->i = new PdfNumber(2.0);
 *   $annotation->be = $be;
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class BorderEffect implements Serializable
{
    public ?PdfName $s = null;    // /S - style: S (none) or C (cloudy)
    public ?PdfNumber $i = null;  // /I - cloud intensity, 0–2 (only meaningful when S=C)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->s !== null) {
            $dict->set('S', $this->s);
        }
        if ($this->i !== null) {
            $dict->set('I', $this->i);
        }

        return $dict->toPdf();
    }
}
