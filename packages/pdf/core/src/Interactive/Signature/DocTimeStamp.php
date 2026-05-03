<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Signature;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

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
#[RequiresPdfVersion(PdfVersion::V1_6)]
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
