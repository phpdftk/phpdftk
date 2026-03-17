<?php

declare(strict_types=1);

namespace Phpdftk\Annotation;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfString;

/**
 * Free text annotation (/Subtype /FreeText).
 * Renders text directly on the page surface.
 */
class FreeTextAnnotation extends Annotation
{
    public PdfString $da;               // /DA - default appearance - required
    public ?int $q = null;              // /Q - justification
    public ?PdfString $rc = null;       // /RC - rich content
    public ?PdfString $ds = null;       // /DS - default style
    public ?PdfReference $cl = null;    // /CL - callout line
    public ?PdfName $it = null;         // /IT - intent
    public ?PdfDictionary $be = null;   // /BE - border effect
    public ?PdfArray $rd = null;        // /RD - rectangle differences
    public ?PdfDictionary $bs = null;   // /BS - border style
    public ?PdfName $le = null;         // /LE - line ending

    public function __construct(\Phpdftk\Core\PdfArray $rect, PdfString $da)
    {
        parent::__construct($rect);
        $this->da = $da;
    }

    public function getSubtype(): string
    {
        return 'FreeText';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();
        $dict->set('DA', $this->da);

        if ($this->q !== null) {
            $dict->set('Q', new PdfNumber($this->q));
        }
        if ($this->rc !== null) {
            $dict->set('RC', $this->rc);
        }
        if ($this->ds !== null) {
            $dict->set('DS', $this->ds);
        }
        if ($this->cl !== null) {
            $dict->set('CL', $this->cl);
        }
        if ($this->it !== null) {
            $dict->set('IT', $this->it);
        }
        if ($this->be !== null) {
            $dict->set('BE', $this->be);
        }
        if ($this->rd !== null) {
            $dict->set('RD', $this->rd);
        }
        if ($this->bs !== null) {
            $dict->set('BS', $this->bs);
        }
        if ($this->le !== null) {
            $dict->set('LE', $this->le);
        }

        return $dict->toPdf();
    }
}
