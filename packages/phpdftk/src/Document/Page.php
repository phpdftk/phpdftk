<?php

declare(strict_types=1);

namespace Phpdftk\Document;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfObject;
use Phpdftk\Core\PdfReference;
use Phpdftk\Content\Resources;

/**
 * PDF Page object (/Type /Page).
 * Represents a single page in the document.
 */
class Page extends PdfObject
{
    public const PDF_TYPE = 'Page';

    public ?PdfReference $parent = null;    // /Parent - required
    public ?Resources $resources = null;    // /Resources - required (inline)
    public ?PdfArray $mediaBox = null;      // /MediaBox
    public ?PdfArray $cropBox = null;       // /CropBox
    public ?PdfArray $bleedBox = null;      // /BleedBox
    public ?PdfArray $trimBox = null;       // /TrimBox
    public ?PdfArray $artBox = null;        // /ArtBox
    /** @var array<int, PdfReference> */
    public array $contents = [];            // /Contents - refs to content streams
    public int $rotate = 0;                 // /Rotate
    /** @var array<int, PdfReference> */
    public array $annots = [];              // /Annots - refs to annotations
    public ?PdfReference $group = null;     // /Group
    public ?PdfReference $thumb = null;     // /Thumb
    public ?PdfNumber $userUnit = null;     // /UserUnit
    public ?int $structParents = null;      // /StructParents
    public ?PdfDictionary $transition = null; // /Trans
    public ?PdfNumber $dur = null;          // /Dur

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->parent !== null) {
            $dict->set('Parent', $this->parent);
        }
        if ($this->resources !== null) {
            $dict->set('Resources', $this->resources);
        }
        if ($this->mediaBox !== null) {
            $dict->set('MediaBox', $this->mediaBox);
        }
        if ($this->cropBox !== null) {
            $dict->set('CropBox', $this->cropBox);
        }
        if ($this->bleedBox !== null) {
            $dict->set('BleedBox', $this->bleedBox);
        }
        if ($this->trimBox !== null) {
            $dict->set('TrimBox', $this->trimBox);
        }
        if ($this->artBox !== null) {
            $dict->set('ArtBox', $this->artBox);
        }

        // /Contents: single ref or array of refs
        if (count($this->contents) === 1) {
            $dict->set('Contents', $this->contents[0]);
        } elseif (count($this->contents) > 1) {
            $dict->set('Contents', new PdfArray($this->contents));
        }

        if ($this->rotate !== 0) {
            $dict->set('Rotate', new PdfNumber($this->rotate));
        }
        if (!empty($this->annots)) {
            $dict->set('Annots', new PdfArray($this->annots));
        }
        if ($this->group !== null) {
            $dict->set('Group', $this->group);
        }
        if ($this->thumb !== null) {
            $dict->set('Thumb', $this->thumb);
        }
        if ($this->userUnit !== null) {
            $dict->set('UserUnit', $this->userUnit);
        }
        if ($this->structParents !== null) {
            $dict->set('StructParents', new PdfNumber($this->structParents));
        }
        if ($this->transition !== null) {
            $dict->set('Trans', $this->transition);
        }
        if ($this->dur !== null) {
            $dict->set('Dur', $this->dur);
        }

        return $dict->toPdf();
    }
}
