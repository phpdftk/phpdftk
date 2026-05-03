<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Serializable;

/**
 * PDF Page Tree node (/Type /Pages).
 * Serves as the root of the page tree and parent of individual Page objects.
 */
class PageTree extends PdfObject
{
    public const PDF_TYPE = 'Pages';

    public ?PdfReference $parent = null;   // /Parent (for non-root nodes)
    /** @var array<int, PdfReference> */
    public array $kids = [];               // /Kids - array of PdfReference to pages/subtrees
    public int $count = 0;                 // /Count - total leaf pages
    public ?PdfArray $mediaBox = null;     // /MediaBox - inherited by pages
    public ?PdfArray $cropBox = null;      // /CropBox - inheritable box
    public ?PdfArray $bleedBox = null;     // /BleedBox - inheritable box
    public ?PdfArray $trimBox = null;      // /TrimBox - inheritable box
    public ?PdfArray $artBox = null;       // /ArtBox - inheritable box
    public ?PdfReference $resources = null; // /Resources - inherited by pages
    public int $rotate = 0;                // /Rotate
    public ?PdfName $tabs = null;          // /Tabs - tab order (R, C, S)
    public ?PdfNumber $userUnit = null;    // /UserUnit - points-per-unit multiplier
    public ?PdfReference $group = null;    // /Group - transparency group (inheritable)
    public ?PdfReference $thumb = null;    // /Thumb - thumbnail image
    public ?PdfArray $b = null;            // /B - article bead refs
    public ?PdfNumber $dur = null;         // /Dur - page display duration
    public Serializable|null $transition = null; // /Trans - transition dict
    public ?PdfArray $annots = null;       // /Annots - inheritable annotations
    public ?PdfReference $aa = null;       // /AA - additional actions
    public ?PdfReference $metadata = null; // /Metadata - XMP stream
    public ?PdfDictionary $pieceInfo = null; // /PieceInfo - application data
    public ?int $structParents = null;     // /StructParents
    public ?PdfString $id = null;          // /ID - page identifier
    public ?PdfNumber $pz = null;          // /PZ - preferred zoom
    public BoxColorInfo|PdfDictionary|null $boxColorInfo = null; // /BoxColorInfo
    public ?PdfArray $af = null;            // /AF - associated files
    public ?PdfArray $outputIntents = null; // /OutputIntents
    public ?PdfReference $dPart = null;     // /DPart
    public ?PdfDictionary $separationInfo = null; // /SeparationInfo
    public ?PdfName $templateInstantiated = null; // /TemplateInstantiated
    public ?PdfReference $presSteps = null; // /PresSteps - presentation steps
    public ?PdfArray $vp = null;           // /VP - viewport array

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->parent !== null) {
            $dict->set('Parent', $this->parent);
        }

        // Build /Kids array
        $kidItems = [];
        foreach ($this->kids as $kid) {
            $kidItems[] = $kid;
        }
        $dict->set('Kids', new PdfArray($kidItems));
        $dict->set('Count', new PdfNumber($this->count));

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
        if ($this->resources !== null) {
            $dict->set('Resources', $this->resources);
        }
        if ($this->rotate !== 0) {
            $dict->set('Rotate', new PdfNumber($this->rotate));
        }
        if ($this->tabs !== null) {
            $dict->set('Tabs', $this->tabs);
        }
        if ($this->userUnit !== null) {
            $dict->set('UserUnit', $this->userUnit);
        }
        if ($this->group !== null) {
            $dict->set('Group', $this->group);
        }
        if ($this->thumb !== null) {
            $dict->set('Thumb', $this->thumb);
        }
        if ($this->b !== null) {
            $dict->set('B', $this->b);
        }
        if ($this->dur !== null) {
            $dict->set('Dur', $this->dur);
        }
        if ($this->transition !== null) {
            $dict->set('Trans', $this->transition);
        }
        if ($this->annots !== null) {
            $dict->set('Annots', $this->annots);
        }
        if ($this->aa !== null) {
            $dict->set('AA', $this->aa);
        }
        if ($this->metadata !== null) {
            $dict->set('Metadata', $this->metadata);
        }
        if ($this->pieceInfo !== null) {
            $dict->set('PieceInfo', $this->pieceInfo);
        }
        if ($this->structParents !== null) {
            $dict->set('StructParents', new PdfNumber($this->structParents));
        }
        if ($this->id !== null) {
            $dict->set('ID', $this->id);
        }
        if ($this->pz !== null) {
            $dict->set('PZ', $this->pz);
        }
        if ($this->boxColorInfo !== null) {
            $dict->set('BoxColorInfo', $this->boxColorInfo);
        }
        if ($this->af !== null) {
            $dict->set('AF', $this->af);
        }
        if ($this->outputIntents !== null) {
            $dict->set('OutputIntents', $this->outputIntents);
        }
        if ($this->dPart !== null) {
            $dict->set('DPart', $this->dPart);
        }
        if ($this->separationInfo !== null) {
            $dict->set('SeparationInfo', $this->separationInfo);
        }
        if ($this->templateInstantiated !== null) {
            $dict->set('TemplateInstantiated', $this->templateInstantiated);
        }
        if ($this->presSteps !== null) {
            $dict->set('PresSteps', $this->presSteps);
        }
        if ($this->vp !== null) {
            $dict->set('VP', $this->vp);
        }

        return $dict->toPdf();
    }
}
