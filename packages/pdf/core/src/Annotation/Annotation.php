<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Abstract base class for all PDF annotation types (/Type /Annot).
 */
abstract class Annotation extends PdfObject
{
    public const PDF_TYPE = 'Annot';

    /**
     * Returns the /Subtype value for this annotation.
     */
    abstract public function getSubtype(): string;

    public PdfArray $rect;                     // /Rect - required [x1 y1 x2 y2]
    public ?PdfString $contents = null;        // /Contents
    public ?PdfReference $p = null;            // /P - page reference
    public ?PdfString $nm = null;              // /NM - annotation name
    public ?PdfString $m = null;               // /M - modification date
    public int $f = 0;                         // /F - flags
    public ?PdfDictionary $ap = null;          // /AP - appearance streams
    public ?PdfName $as = null;                // /AS - appearance state
    public ?PdfArray $border = null;           // /Border
    public ?PdfArray $c = null;                // /C - color
    public ?int $structParent = null;          // /StructParent
    public ?PdfReference $oc = null;           // /OC
    public ?Serializable $bs = null;           // /BS - BorderStyle dict

    public function __construct(PdfArray $rect)
    {
        $this->rect = $rect;
    }

    /**
     * Build the common annotation dictionary entries.
     * Subclasses call this and add their own entries.
     */
    protected function buildDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Subtype', new PdfName($this->getSubtype()));
        $dict->set('Rect', $this->rect);

        if ($this->contents !== null) {
            $dict->set('Contents', $this->contents);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->nm !== null) {
            $dict->set('NM', $this->nm);
        }
        if ($this->m !== null) {
            $dict->set('M', $this->m);
        }
        if ($this->f !== 0) {
            $dict->set('F', new PdfNumber($this->f));
        }
        if ($this->ap !== null) {
            $dict->set('AP', $this->ap);
        }
        if ($this->as !== null) {
            $dict->set('AS', $this->as);
        }
        if ($this->border !== null) {
            $dict->set('Border', $this->border);
        }
        if ($this->c !== null) {
            $dict->set('C', $this->c);
        }
        if ($this->structParent !== null) {
            $dict->set('StructParent', new PdfNumber($this->structParent));
        }
        if ($this->oc !== null) {
            $dict->set('OC', $this->oc);
        }
        if ($this->bs !== null) {
            $dict->set('BS', $this->bs);
        }

        return $dict;
    }

    public function toPdf(): string
    {
        return $this->buildDictionary()->toPdf();
    }
}
