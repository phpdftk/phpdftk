<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\ThreeD;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * 3D view dictionary (/Type /3DView) — ISO 32000-2 §13.6.4.
 *
 * Describes a named camera view of a 3D model.
 */
class ThreeDView extends PdfObject
{
    public const PDF_TYPE = '3DView';

    public PdfString $xn;                                 // /XN  external name (required)
    public ?PdfString $in = null;                         // /IN  internal name
    public ?PdfName $ms = null;                           // /MS  matrix source (M, U3D)
    public ?PdfArray $c2w = null;                         // /C2W camera-to-world matrix
    public ?float $co = null;                             // /CO  center-of-orbit distance
    public ?PdfDictionary $p = null;                      // /P   projection dict
    public ?PdfArray $o = null;                           // /O   overlay array
    public ThreeDBackground|PdfReference|null $bg = null; // /BG  background
    public ThreeDRenderMode|PdfReference|null $rm = null; // /RM  render mode
    public ThreeDLightingScheme|PdfReference|null $ls = null; // /LS lighting
    public ?PdfArray $sa = null;                          // /SA  cross sections
    public ?PdfArray $na = null;                          // /NA  node overrides
    public ?bool $nr = null;                              // /NR  render rail

    public function __construct(string $externalName)
    {
        $this->xn = new PdfString($externalName);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('XN', $this->xn);
        if ($this->in !== null) {
            $dict->set('IN', $this->in);
        }
        if ($this->ms !== null) {
            $dict->set('MS', $this->ms);
        }
        if ($this->c2w !== null) {
            $dict->set('C2W', $this->c2w);
        }
        if ($this->co !== null) {
            $dict->set('CO', new PdfNumber($this->co));
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->o !== null) {
            $dict->set('O', $this->o);
        }
        if ($this->bg !== null) {
            $dict->set('BG', $this->bg);
        }
        if ($this->rm !== null) {
            $dict->set('RM', $this->rm);
        }
        if ($this->ls !== null) {
            $dict->set('LS', $this->ls);
        }
        if ($this->sa !== null) {
            $dict->set('SA', $this->sa);
        }
        if ($this->na !== null) {
            $dict->set('NA', $this->na);
        }
        if ($this->nr !== null) {
            $dict->set('NR', new PdfBoolean($this->nr));
        }
        return $dict->toPdf();
    }
}
