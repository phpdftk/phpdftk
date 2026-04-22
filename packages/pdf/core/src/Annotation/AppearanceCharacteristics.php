<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Widget annotation appearance characteristics (ISO 32000-2 Table 192).
 *
 * Describes the visual characteristics of a widget annotation's appearance.
 * Assigned to the /MK entry of widget annotation dictionaries.
 *
 * Example:
 *   $mk = new AppearanceCharacteristics();
 *   $mk->r = 90;
 *   $mk->bc = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
 *   $mk->ca = new PdfString('Submit');
 *   $widget->mk = $mk;
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class AppearanceCharacteristics implements Serializable
{
    public ?int $r = null;              // /R - rotation (0, 90, 180, 270)
    public ?PdfArray $bc = null;        // /BC - border color
    public ?PdfArray $bg = null;        // /BG - background color
    public ?PdfString $ca = null;       // /CA - normal caption
    public ?PdfString $rc = null;       // /RC - rollover caption
    public ?PdfString $ac = null;       // /AC - alternate caption
    public ?PdfReference $i = null;     // /I - normal icon
    public ?PdfReference $ri = null;    // /RI - rollover icon
    public ?PdfReference $ix = null;    // /IX - alternate icon
    public ?PdfDictionary $if_ = null;  // /IF - icon fit dict (if is reserved)
    public ?int $tp = null;             // /TP - text position

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->r !== null) {
            $dict->set('R', new PdfNumber($this->r));
        }
        if ($this->bc !== null) {
            $dict->set('BC', $this->bc);
        }
        if ($this->bg !== null) {
            $dict->set('BG', $this->bg);
        }
        if ($this->ca !== null) {
            $dict->set('CA', $this->ca);
        }
        if ($this->rc !== null) {
            $dict->set('RC', $this->rc);
        }
        if ($this->ac !== null) {
            $dict->set('AC', $this->ac);
        }
        if ($this->i !== null) {
            $dict->set('I', $this->i);
        }
        if ($this->ri !== null) {
            $dict->set('RI', $this->ri);
        }
        if ($this->ix !== null) {
            $dict->set('IX', $this->ix);
        }
        if ($this->if_ !== null) {
            $dict->set('IF', $this->if_);
        }
        if ($this->tp !== null) {
            $dict->set('TP', new PdfNumber($this->tp));
        }

        return $dict->toPdf();
    }
}
