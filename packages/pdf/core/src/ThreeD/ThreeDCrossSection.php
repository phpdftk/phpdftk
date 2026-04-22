<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\ThreeD;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * 3D cross-section dictionary (/Type /3DCrossSection) —
 * ISO 32000-2 §13.6.8.
 *
 * Describes a clipping plane used to slice the 3D model.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class ThreeDCrossSection extends PdfObject
{
    public const PDF_TYPE = '3DCrossSection';

    public ?PdfArray $c = null;    // /C  center point
    public ?PdfArray $o = null;    // /O  orientation
    public ?PdfArray $pc = null;   // /PC plane color (3-element)
    public ?float $pO = null;      // /PO plane opacity
    public ?bool $iv = null;       // /IV show intersection
    public ?PdfArray $ic = null;   // /IC intersection color
    public ?bool $st = null;       // /ST show transparent

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        if ($this->c !== null) {
            $dict->set('C', $this->c);
        }
        if ($this->o !== null) {
            $dict->set('O', $this->o);
        }
        if ($this->pc !== null) {
            $dict->set('PC', $this->pc);
        }
        if ($this->pO !== null) {
            $dict->set('PO', $this->pO);
        }
        if ($this->iv !== null) {
            $dict->set('IV', new PdfBoolean($this->iv));
        }
        if ($this->ic !== null) {
            $dict->set('IC', $this->ic);
        }
        if ($this->st !== null) {
            $dict->set('ST', new PdfBoolean($this->st));
        }
        return $dict->toPdf();
    }
}
