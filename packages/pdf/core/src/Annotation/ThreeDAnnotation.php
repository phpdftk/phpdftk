<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * 3D annotation (/Subtype /3D).
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class ThreeDAnnotation extends Annotation
{
    public ?PdfReference $dd = null;    // /3DD - 3D stream
    public ?PdfDictionary $dv = null;   // /3DV - default view
    public ?PdfDictionary $da = null;   // /3DA - activation dict
    public ?bool $di = null;            // /3DI - interactive
    public ?PdfArray $db = null;        // /3DB - 3D box

    public function getSubtype(): string
    {
        return '3D';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->dd !== null) {
            $dict->set('3DD', $this->dd);
        }
        if ($this->dv !== null) {
            $dict->set('3DV', $this->dv);
        }
        if ($this->da !== null) {
            $dict->set('3DA', $this->da);
        }
        if ($this->di !== null) {
            $dict->set('3DI', new PdfBoolean($this->di));
        }
        if ($this->db !== null) {
            $dict->set('3DB', $this->db);
        }

        return $dict->toPdf();
    }
}
