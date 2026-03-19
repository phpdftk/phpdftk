<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;

/**
 * External Graphics State parameter dictionary (/Type /ExtGState).
 */
class ExtGState extends PdfObject
{
    public const PDF_TYPE = 'ExtGState';

    public ?float $lw = null;        // /LW - line width
    public ?int $lc = null;          // /LC - line cap style
    public ?int $lj = null;          // /LJ - line join style
    public ?float $ml = null;        // /ML - miter limit
    public ?PdfArray $d = null;      // /D - dash pattern
    public ?PdfName $ri = null;      // /RI - rendering intent
    public ?bool $op = null;         // /OP - overprint stroke
    public ?bool $opLower = null;    // /op - overprint fill
    public ?int $opm = null;         // /OPM - overprint mode
    public ?PdfArray $font = null;   // /Font
    public ?float $fl = null;        // /FL - flatness
    public ?float $sm = null;        // /SM - smoothness
    public ?bool $sa = null;         // /SA - stroke adjustment
    public mixed $bm = null;         // /BM - blend mode
    public mixed $sMask = null;      // /SMask - soft mask
    public ?float $ca = null;        // /CA - stroke alpha
    public ?float $caLower = null;   // /ca - fill alpha
    public ?bool $ais = null;        // /AIS - alpha is shape
    public ?bool $tk = null;         // /TK - text knockout

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->lw !== null) {
            $dict->set('LW', new PdfNumber($this->lw));
        }
        if ($this->lc !== null) {
            $dict->set('LC', new PdfNumber($this->lc));
        }
        if ($this->lj !== null) {
            $dict->set('LJ', new PdfNumber($this->lj));
        }
        if ($this->ml !== null) {
            $dict->set('ML', new PdfNumber($this->ml));
        }
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }
        if ($this->ri !== null) {
            $dict->set('RI', $this->ri);
        }
        if ($this->op !== null) {
            $dict->set('OP', new PdfBoolean($this->op));
        }
        if ($this->opLower !== null) {
            $dict->set('op', new PdfBoolean($this->opLower));
        }
        if ($this->opm !== null) {
            $dict->set('OPM', new PdfNumber($this->opm));
        }
        if ($this->font !== null) {
            $dict->set('Font', $this->font);
        }
        if ($this->fl !== null) {
            $dict->set('FL', new PdfNumber($this->fl));
        }
        if ($this->sm !== null) {
            $dict->set('SM', new PdfNumber($this->sm));
        }
        if ($this->sa !== null) {
            $dict->set('SA', new PdfBoolean($this->sa));
        }
        if ($this->bm !== null) {
            if ($this->bm instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('BM', $this->bm);
            } else {
                $dict->set('BM', new PdfName((string) $this->bm));
            }
        }
        if ($this->sMask !== null) {
            if ($this->sMask instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('SMask', $this->sMask);
            } else {
                $dict->set('SMask', new PdfName((string) $this->sMask));
            }
        }
        if ($this->ca !== null) {
            $dict->set('CA', new PdfNumber($this->ca));
        }
        if ($this->caLower !== null) {
            $dict->set('ca', new PdfNumber($this->caLower));
        }
        if ($this->ais !== null) {
            $dict->set('AIS', new PdfBoolean($this->ais));
        }
        if ($this->tk !== null) {
            $dict->set('TK', new PdfBoolean($this->tk));
        }

        return $dict->toPdf();
    }
}
