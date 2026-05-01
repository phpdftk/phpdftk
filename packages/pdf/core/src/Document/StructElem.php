<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\PdfVersionAware;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Structure Element (ISO 32000-2 Table 324).
 *
 * Represents a node in the structure tree for tagged PDF.
 *
 * Example:
 *   $elem = new StructElem('P');
 *   $elem->p = new PdfReference($parent->objectNumber);
 *   $elem->t = new PdfString('Paragraph Title');
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class StructElem extends PdfObject implements PdfVersionAware
{
    public const PDF_TYPE = 'StructElem';

    public PdfName $s;                              // /S - structure type (required)
    public ?PdfReference $p = null;                 // /P - parent element
    public ?PdfString $id = null;                   // /ID
    public ?PdfReference $pg = null;                // /Pg - page
    public PdfReference|PdfArray|null $k = null;    // /K - children
    public ?PdfArray $a = null;                     // /A - attribute objects
    public ?PdfArray $c = null;                     // /C - class names
    public ?int $r = null;                          // /R - revision number
    public ?PdfString $t = null;                    // /T - title
    public ?PdfString $lang = null;                 // /Lang
    public ?PdfString $alt = null;                  // /Alt - alternate description
    public ?PdfString $e = null;                    // /E - expanded form
    public ?PdfString $actualText = null;           // /ActualText

    public function __construct(string $structureType)
    {
        $this->s = new PdfName($structureType);
    }

    public function getMinimumPdfVersion(): ?PdfVersion
    {
        return StandardStructureType::minimumVersion($this->s->value);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', $this->s);

        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->id !== null) {
            $dict->set('ID', $this->id);
        }
        if ($this->pg !== null) {
            $dict->set('Pg', $this->pg);
        }
        if ($this->k !== null) {
            $dict->set('K', $this->k);
        }
        if ($this->a !== null) {
            $dict->set('A', $this->a);
        }
        if ($this->c !== null) {
            $dict->set('C', $this->c);
        }
        if ($this->r !== null) {
            $dict->set('R', new PdfNumber($this->r));
        }
        if ($this->t !== null) {
            $dict->set('T', $this->t);
        }
        if ($this->lang !== null) {
            $dict->set('Lang', $this->lang);
        }
        if ($this->alt !== null) {
            $dict->set('Alt', $this->alt);
        }
        if ($this->e !== null) {
            $dict->set('E', $this->e);
        }
        if ($this->actualText !== null) {
            $dict->set('ActualText', $this->actualText);
        }

        return $dict->toPdf();
    }
}
