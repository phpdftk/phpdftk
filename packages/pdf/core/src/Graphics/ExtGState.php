<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * External Graphics State parameter dictionary (/Type /ExtGState).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
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
    #[RequiresPdfVersion(PdfVersion::V1_4)]
    public mixed $bm = null;         // /BM - blend mode
    #[RequiresPdfVersion(PdfVersion::V1_4)]
    public mixed $sMask = null;      // /SMask - soft mask
    #[RequiresPdfVersion(PdfVersion::V1_4)]
    public ?float $ca = null;        // /CA - stroke alpha
    #[RequiresPdfVersion(PdfVersion::V1_4)]
    public ?float $caLower = null;   // /ca - fill alpha
    #[RequiresPdfVersion(PdfVersion::V1_4)]
    public ?bool $ais = null;        // /AIS - alpha is shape
    #[RequiresPdfVersion(PdfVersion::V1_4)]
    public ?bool $tk = null;         // /TK - text knockout
    public mixed $bg = null;         // /BG - black generation function
    public mixed $bg2 = null;        // /BG2 - black generation (PDF 1.3+)
    public mixed $ucr = null;        // /UCR - undercolor removal
    public mixed $ucr2 = null;       // /UCR2 - undercolor removal (PDF 1.3+)
    public mixed $tr = null;         // /TR - transfer function
    public mixed $tr2 = null;        // /TR2 - transfer function (PDF 1.3+)
    public mixed $ht = null;         // /HT - halftone dict/stream or /Default
    public ?PdfName $useBlackPtComp = null; // /UseBlackPtComp - black point compensation
    public ?PdfArray $hto = null;    // /HTO - halftone origin [x, y]

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
        if ($this->bg !== null) {
            if ($this->bg instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('BG', $this->bg);
            } else {
                $dict->set('BG', new PdfName((string) $this->bg));
            }
        }
        if ($this->bg2 !== null) {
            if ($this->bg2 instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('BG2', $this->bg2);
            } else {
                $dict->set('BG2', new PdfName((string) $this->bg2));
            }
        }
        if ($this->ucr !== null) {
            if ($this->ucr instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('UCR', $this->ucr);
            } else {
                $dict->set('UCR', new PdfName((string) $this->ucr));
            }
        }
        if ($this->ucr2 !== null) {
            if ($this->ucr2 instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('UCR2', $this->ucr2);
            } else {
                $dict->set('UCR2', new PdfName((string) $this->ucr2));
            }
        }
        if ($this->tr !== null) {
            if ($this->tr instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('TR', $this->tr);
            } else {
                $dict->set('TR', new PdfName((string) $this->tr));
            }
        }
        if ($this->tr2 !== null) {
            if ($this->tr2 instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('TR2', $this->tr2);
            } else {
                $dict->set('TR2', new PdfName((string) $this->tr2));
            }
        }
        if ($this->ht !== null) {
            if ($this->ht instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('HT', $this->ht);
            } else {
                $dict->set('HT', new PdfName((string) $this->ht));
            }
        }
        if ($this->useBlackPtComp !== null) {
            $dict->set('UseBlackPtComp', $this->useBlackPtComp);
        }
        if ($this->hto !== null) {
            $dict->set('HTO', $this->hto);
        }

        return $dict->toPdf();
    }
}
