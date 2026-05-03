<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Shared base for markup annotations — ISO 32000-2 §12.5.6.2, Table 170.
 *
 * Markup annotations are the subset of annotations that represent
 * user-authored commentary on the document (Text, FreeText, Line,
 * Square, Circle, Polygon, PolyLine, Highlight, Underline, Squiggly,
 * StrikeOut, Stamp, Caret, Ink, FileAttachment, Sound, Redact). They
 * share a set of fields beyond the base annotation dictionary for
 * authoring metadata, threaded replies, popup windows, and rich content.
 *
 * Non-markup annotations (Link, Popup, Widget, Screen, PrinterMark,
 * TrapNet, Watermark, 3D, Projection, RichMedia, Movie) keep extending
 * {@see Annotation} directly.
 */
#[RequiresPdfVersion(PdfVersion::V1_4)]
abstract class MarkupAnnotation extends Annotation
{
    public ?PdfString $t = null;              // /T            text label (author)
    public ?PdfReference $popup = null;       // /Popup        associated popup annotation
    public ?float $markupCa = null;           // /CA           constant opacity (markup override)
    public ?PdfString $rc = null;             // /RC           rich content (XFA-style XML)
    public ?PdfString $creationDate = null;   // /CreationDate
    public ?PdfReference $irt = null;         // /IRT          in-reply-to annotation
    public ?PdfString $subj = null;           // /Subj         short description
    public ?PdfName $rt = null;               // /RT           reply type: R (reply) or Group
    public ?PdfName $it = null;               // /IT           intent (e.g. FreeTextCallout, LineArrow)
    public ?PdfDictionary $exData = null;     // /ExData       external data dict

    protected function buildDictionary(): PdfDictionary
    {
        $dict = parent::buildDictionary();

        if ($this->t !== null) {
            $dict->set('T', $this->t);
        }
        if ($this->popup !== null) {
            $dict->set('Popup', $this->popup);
        }
        if ($this->markupCa !== null) {
            $dict->set('CA', new \Phpdftk\Pdf\Core\PdfNumber($this->markupCa));
        }
        if ($this->rc !== null) {
            $dict->set('RC', $this->rc);
        }
        if ($this->creationDate !== null) {
            $dict->set('CreationDate', $this->creationDate);
        }
        if ($this->irt !== null) {
            $dict->set('IRT', $this->irt);
        }
        if ($this->subj !== null) {
            $dict->set('Subj', $this->subj);
        }
        if ($this->rt !== null) {
            $dict->set('RT', $this->rt);
        }
        if ($this->it !== null) {
            $dict->set('IT', $this->it);
        }
        if ($this->exData !== null) {
            $dict->set('ExData', $this->exData);
        }

        return $dict;
    }
}
