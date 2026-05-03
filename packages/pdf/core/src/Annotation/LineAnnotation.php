<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Line annotation (/Subtype /Line).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class LineAnnotation extends MarkupAnnotation
{
    public ?PdfArray $l = null;        // /L - two points [x1 y1 x2 y2]
    public ?PdfArray $le = null;       // /LE - line ending styles
    public ?PdfArray $ic = null;       // /IC - interior color
    public ?PdfNumber $ll = null;      // /LL - leader line length
    public ?PdfNumber $lle = null;     // /LLE - leader line extension
    public ?bool $cap = null;          // /Cap - caption flag
    public ?PdfName $it = null;        // /IT - intent
    public ?PdfNumber $llo = null;     // /LLO - leader line offset
    public ?PdfName $cp = null;        // /CP - caption position
    public ?PdfReference $measure = null; // /Measure
    public ?PdfArray $co = null;       // /CO - caption offset

    public function getSubtype(): string
    {
        return 'Line';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->l !== null) {
            $dict->set('L', $this->l);
        }
        if ($this->le !== null) {
            $dict->set('LE', $this->le);
        }
        if ($this->ic !== null) {
            $dict->set('IC', $this->ic);
        }
        if ($this->ll !== null) {
            $dict->set('LL', $this->ll);
        }
        if ($this->lle !== null) {
            $dict->set('LLE', $this->lle);
        }
        if ($this->cap !== null) {
            $dict->set('Cap', new PdfBoolean($this->cap));
        }
        if ($this->it !== null) {
            $dict->set('IT', $this->it);
        }
        if ($this->llo !== null) {
            $dict->set('LLO', $this->llo);
        }
        if ($this->cp !== null) {
            $dict->set('CP', $this->cp);
        }
        if ($this->measure !== null) {
            $dict->set('Measure', $this->measure);
        }
        if ($this->co !== null) {
            $dict->set('CO', $this->co);
        }

        return $dict->toPdf();
    }
}
