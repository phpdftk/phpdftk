<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Free text annotation (/Subtype /FreeText).
 * Renders text directly on the page surface.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class FreeTextAnnotation extends MarkupAnnotation
{
    public PdfString $da;               // /DA - default appearance - required
    public ?int $q = null;              // /Q - justification
    public ?PdfString $rc = null;       // /RC - rich content
    public ?PdfString $ds = null;       // /DS - default style
    public ?PdfReference $cl = null;    // /CL - callout line
    public ?PdfName $it = null;         // /IT - intent
    public ?BorderEffect $be = null;    // /BE - border effect
    public ?PdfArray $rd = null;        // /RD - rectangle differences
    public ?PdfName $le = null;         // /LE - line ending

    public function __construct(\ApprLabs\Pdf\Core\PdfArray $rect, PdfString $da)
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
        if ($this->le !== null) {
            $dict->set('LE', $this->le);
        }

        return $dict->toPdf();
    }
}
