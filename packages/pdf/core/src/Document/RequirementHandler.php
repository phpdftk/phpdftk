<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Requirement handler dictionary (/Type /ReqHandler) —
 * ISO 32000-2 §12.10, Table 254.
 */
#[RequiresPdfVersion(PdfVersion::V1_7)]
class RequirementHandler extends PdfObject
{
    public const PDF_TYPE = 'ReqHandler';

    public PdfName $s;                 // /S - handler type (JS, NoOp, …)
    public ?PdfString $script = null;  // /Script

    public function __construct(string $subtype)
    {
        $this->s = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', $this->s);
        if ($this->script !== null) {
            $dict->set('Script', $this->script);
        }
        return $dict->toPdf();
    }
}
