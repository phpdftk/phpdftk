<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\DeprecatedPdfFeature;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Movie action (/S /Movie) — ISO 32000-2 §12.6.4.9 (deprecated in 2.0 in
 * favor of RichMedia, but still part of the spec).
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
#[DeprecatedPdfFeature(since: '2.0', replacement: 'RichMediaExecuteAction', removedIn: '2.0')]
class MovieAction extends Action
{
    public ?PdfReference $annotation = null;   // /Annotation
    public ?PdfString $t = null;               // /T  - movie title
    public ?PdfName $operation = null;         // /Operation Play|Stop|Pause|Resume

    public function getActionType(): string
    {
        return 'Movie';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->annotation !== null) {
            $dict->set('Annotation', $this->annotation);
        }
        if ($this->t !== null) {
            $dict->set('T', $this->t);
        }
        if ($this->operation !== null) {
            $dict->set('Operation', $this->operation);
        }
        return $dict->toPdf();
    }
}
