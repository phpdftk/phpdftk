<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Document-level timestamp signature (/Type /DocTimeStamp) —
 * ISO 32000-2 §12.8.5 / PAdES Part 4.
 *
 * Wraps an RFC 3161 timestamp token (produced by a Time-Stamping
 * Authority) in the same byte-range + /Contents placeholder structure as
 * a regular signature. /SubFilter defaults to ETSI.RFC3161 and /Type is
 * /DocTimeStamp rather than /Sig.
 *
 * Structurally identical to {@see SignatureValue}, so all byte-range and
 * /Contents placeholder handling in `PdfWriter` works unchanged.
 */
class DocTimeStamp extends SignatureValue
{
    public const PDF_TYPE = 'DocTimeStamp';

    public function __construct(
        string $filter = 'Adobe.PPKLite',
        ?string $subFilter = 'ETSI.RFC3161',
        ?PdfString $contents = null
    ) {
        parent::__construct($filter, $subFilter, $contents);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Filter', $this->filter);
        if ($this->subFilter !== null) {
            $dict->set('SubFilter', $this->subFilter);
        }
        $dict->set('Contents', $this->contents);
        if ($this->byteRange !== null) {
            $dict->set('ByteRange', $this->byteRange);
        }
        if ($this->v !== null) {
            $dict->set('V', new PdfNumber($this->v));
        }
        return $dict->toPdf();
    }
}
