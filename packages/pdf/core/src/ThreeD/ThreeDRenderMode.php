<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\ThreeD;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * 3D render mode dictionary (/Type /3DRenderMode) — ISO 32000-2 §13.6.6.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class ThreeDRenderMode extends PdfObject
{
    public const PDF_TYPE = '3DRenderMode';

    public PdfName $subtype;        // /Subtype - required render mode
    public ?PdfArray $ac = null;    // /AC auxiliary color
    public ?PdfArray $fc = null;    // /FC face color
    public ?float $op = null;       // /OP opacity
    public ?bool $cv = null;        // /CV crease value

    public function __construct(string $subtype)
    {
        $this->subtype = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Subtype', $this->subtype);
        if ($this->ac !== null) {
            $dict->set('AC', $this->ac);
        }
        if ($this->fc !== null) {
            $dict->set('FC', $this->fc);
        }
        if ($this->op !== null) {
            $dict->set('Opacity', new PdfNumber($this->op));
        }
        if ($this->cv !== null) {
            $dict->set('CV', new \ApprLabs\Pdf\Core\PdfBoolean($this->cv));
        }
        return $dict->toPdf();
    }
}
