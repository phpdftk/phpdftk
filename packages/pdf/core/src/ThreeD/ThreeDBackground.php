<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\ThreeD;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * 3D background dictionary (/Type /3DBG) — ISO 32000-2 §13.6.5.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class ThreeDBackground extends PdfObject
{
    public const PDF_TYPE = '3DBG';

    public ColorSpace|PdfName|PdfArray|null $cs = null;  // /CS colorspace
    public ?PdfArray $c = null;                          // /C  color
    public ?bool $ea = null;                             // /EA apply also to crossing sections

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->cs !== null) {
            $dict->set('CS', $this->cs);
        }
        if ($this->c !== null) {
            $dict->set('C', $this->c);
        }
        if ($this->ea !== null) {
            $dict->set('EA', new PdfBoolean($this->ea));
        }
        return $dict->toPdf();
    }
}
