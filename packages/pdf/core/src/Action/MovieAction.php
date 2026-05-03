<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\DeprecatedPdfFeature;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

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
