<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Soft Mask dictionary (ISO 32000-2 Table 131).
 */
#[RequiresPdfVersion(PdfVersion::V1_4)]
class SoftMask implements Serializable
{
    public PdfName $s;              // /S - required (Alpha or Luminosity)
    public PdfReference $g;         // /G - required (transparency group XObject)
    public ?PdfArray $bc = null;    // /BC - backdrop color
    public mixed $tr = null;        // /TR - transfer function

    public function __construct(string $subtype, PdfReference $group)
    {
        $this->s = new PdfName($subtype);
        $this->g = $group;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Mask'));
        $dict->set('S', $this->s);
        $dict->set('G', $this->g);

        if ($this->bc !== null) {
            $dict->set('BC', $this->bc);
        }
        if ($this->tr !== null) {
            if ($this->tr instanceof Serializable) {
                $dict->set('TR', $this->tr);
            } else {
                $dict->set('TR', new PdfName((string) $this->tr));
            }
        }

        return $dict->toPdf();
    }
}
