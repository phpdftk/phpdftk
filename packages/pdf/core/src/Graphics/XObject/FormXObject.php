<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\XObject;

use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\Document\GroupAttributes;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Form XObject (/Subtype /Form) — ISO 32000-2 §8.10, Table 95.
 *
 * A self-contained content stream that can be placed on a page. Carries
 * its own resource dictionary, optional transparency group, optional
 * reference-XObject pointer, and optional metadata.
 */
class FormXObject extends PdfStream
{
    public const PDF_TYPE    = 'XObject';
    public const PDF_SUBTYPE = 'Form';

    public PdfArray $bBox;                                     // /BBox - required
    public ?PdfArray $matrix = null;                           // /Matrix
    public Resources|PdfDictionary|null $resources = null;     // /Resources
    public ?PdfName $formType = null;                          // /FormType
    public GroupAttributes|Serializable|null $group = null;    // /Group - transparency group
    public ?PdfReference $ref = null;                          // /Ref - reference XObject
    public ?PdfReference $metadata = null;                     // /Metadata - XMP stream
    public ?PdfReference $pieceInfo = null;                    // /PieceInfo
    public ?PdfString $lastModified = null;                    // /LastModified
    public ?int $structParent = null;                          // /StructParent
    public ?int $structParents = null;                         // /StructParents
    public ?PdfReference $oc = null;                           // /OC - optional content
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public ?PdfArray $af = null;                               // /AF - associated files
    public ?PdfDictionary $opi = null;                         // /OPI
    public ?PdfReference $measure = null;                      // /Measure
    public ?PdfReference $ptData = null;                       // /PtData
    public ?PdfName $name = null;                              // /Name - deprecated but permitted

    public function __construct(PdfArray $bBox, string $data = '')
    {
        parent::__construct(new PdfDictionary(), $data);
        $this->bBox = $bBox;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('Subtype', new PdfName(self::PDF_SUBTYPE));
        if ($this->formType !== null) {
            $this->dictionary->set('FormType', $this->formType);
        }
        $this->dictionary->set('BBox', $this->bBox);

        if ($this->matrix !== null) {
            $this->dictionary->set('Matrix', $this->matrix);
        }
        if ($this->resources !== null) {
            $this->dictionary->set('Resources', $this->resources);
        }
        if ($this->group !== null) {
            $this->dictionary->set('Group', $this->group);
        }
        if ($this->ref !== null) {
            $this->dictionary->set('Ref', $this->ref);
        }
        if ($this->metadata !== null) {
            $this->dictionary->set('Metadata', $this->metadata);
        }
        if ($this->pieceInfo !== null) {
            $this->dictionary->set('PieceInfo', $this->pieceInfo);
        }
        if ($this->lastModified !== null) {
            $this->dictionary->set('LastModified', $this->lastModified);
        }
        if ($this->structParent !== null) {
            $this->dictionary->set('StructParent', new PdfNumber($this->structParent));
        }
        if ($this->structParents !== null) {
            $this->dictionary->set('StructParents', new PdfNumber($this->structParents));
        }
        if ($this->oc !== null) {
            $this->dictionary->set('OC', $this->oc);
        }
        if ($this->af !== null) {
            $this->dictionary->set('AF', $this->af);
        }
        if ($this->opi !== null) {
            $this->dictionary->set('OPI', $this->opi);
        }
        if ($this->measure !== null) {
            $this->dictionary->set('Measure', $this->measure);
        }
        if ($this->ptData !== null) {
            $this->dictionary->set('PtData', $this->ptData);
        }
        if ($this->name !== null) {
            $this->dictionary->set('Name', $this->name);
        }

        return parent::toPdf();
    }
}
