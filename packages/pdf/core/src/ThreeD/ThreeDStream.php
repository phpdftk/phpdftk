<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\ThreeD;

use ApprLabs\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;

/**
 * 3D stream (/Type /3D) — ISO 32000-2 §13.6.3.
 *
 * Carries the 3D artwork (U3D or PRC bytes) referenced by a 3D annotation.
 *
 * Required: Subtype (U3D or PRC). Optional: VA (views), DV (default view),
 * AN (animation style), ColorSpace, Resources.
 */
class ThreeDStream extends PdfStream
{
    public const PDF_TYPE = '3D';

    public PdfName $subtype;                           // /Subtype
    public ?PdfArray $va = null;                       // /VA views
    public mixed $dv = null;                           // /DV default view
    public ?PdfDictionary $an = null;                  // /AN animation
    public ColorSpace|PdfName|PdfArray|null $colorSpace = null; // /ColorSpace
    public ?PdfDictionary $resources = null;           // /Resources
    public ?PdfString $oncInstantiate = null;          // /OnInstantiate — callback JS

    public function __construct(string $subtype, string $bytes = '')
    {
        parent::__construct(new PdfDictionary(), $bytes);
        $this->subtype = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('Subtype', $this->subtype);
        if ($this->va !== null) {
            $this->dictionary->set('VA', $this->va);
        }
        if ($this->dv !== null) {
            $this->dictionary->set('DV', $this->dv);
        }
        if ($this->an !== null) {
            $this->dictionary->set('AN', $this->an);
        }
        if ($this->colorSpace !== null) {
            $this->dictionary->set('ColorSpace', $this->colorSpace);
        }
        if ($this->resources !== null) {
            $this->dictionary->set('Resources', $this->resources);
        }
        if ($this->oncInstantiate !== null) {
            $this->dictionary->set('OnInstantiate', $this->oncInstantiate);
        }
        return parent::toPdf();
    }
}
