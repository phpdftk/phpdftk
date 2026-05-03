<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Redact annotation (/Subtype /Redact).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class RedactAnnotation extends MarkupAnnotation
{
    public ?PdfArray $quadPoints = null;    // /QuadPoints
    public ?PdfArray $ic = null;            // /IC - interior color
    public ?PdfReference $ro = null;        // /RO - rollover appearance
    public ?PdfString $overlayText = null;  // /OverlayText
    public ?bool $repeat = null;            // /Repeat
    public ?PdfString $da = null;           // /DA - default appearance
    public ?int $q = null;                  // /Q - quadding (0=left, 1=center, 2=right)

    public function getSubtype(): string
    {
        return 'Redact';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->quadPoints !== null) {
            $dict->set('QuadPoints', $this->quadPoints);
        }
        if ($this->ic !== null) {
            $dict->set('IC', $this->ic);
        }
        if ($this->ro !== null) {
            $dict->set('RO', $this->ro);
        }
        if ($this->overlayText !== null) {
            $dict->set('OverlayText', $this->overlayText);
        }
        if ($this->repeat !== null) {
            $dict->set('Repeat', new PdfBoolean($this->repeat));
        }
        if ($this->da !== null) {
            $dict->set('DA', $this->da);
        }
        if ($this->q !== null) {
            $dict->set('Q', new PdfNumber($this->q));
        }

        return $dict->toPdf();
    }
}
