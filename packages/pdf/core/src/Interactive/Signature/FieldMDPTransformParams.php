<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * FieldMDP transform parameters — ISO 32000-2 §12.8.2.4, Table 255.
 *
 * Locks specific form fields from further modification after signing.
 *   /Action /All      — lock every field
 *   /Action /Include  — lock the fields listed in /Fields
 *   /Action /Exclude  — lock everything except the fields listed in /Fields
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class FieldMDPTransformParams extends TransformParams
{
    public PdfName $action;        // /Action
    public ?PdfArray $fields = null; // /Fields  (required unless Action = /All)
    public ?PdfName $v = null;     // /V

    public function __construct(string $action = 'All', ?PdfArray $fields = null)
    {
        $this->action = new PdfName($action);
        $this->fields = $fields;
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('Action', $this->action);
        if ($this->fields !== null) {
            $dict->set('Fields', $this->fields);
        }
        if ($this->v !== null) {
            $dict->set('V', $this->v);
        }
        return $dict->toPdf();
    }
}
