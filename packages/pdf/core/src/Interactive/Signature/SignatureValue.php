<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Signature value dictionary (/Type /Sig) — ISO 32000-2 §12.8.1, Table 258.
 *
 * Holds the signed bytes (/Contents), the byte range over the PDF that was
 * signed (/ByteRange), the handler that produced the signature (/Filter,
 * /SubFilter), and optional metadata (name, location, reason, contact,
 * date, references).
 *
 * Actual PKCS#7/CAdES signing is out of scope for the object model; this
 * class only carries the placeholder and structural entries. Callers that
 * implement signing compute /ByteRange and overwrite /Contents in the
 * serialized PDF.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class SignatureValue extends PdfObject
{
    public const PDF_TYPE = 'Sig';

    public PdfName $filter;               // /Filter     (required)
    public ?PdfName $subFilter = null;    // /SubFilter
    public PdfString $contents;           // /Contents   (required, usually hex)
    public ?PdfArray $cert = null;        // /Cert       chain (single or array)
    public ?PdfArray $byteRange = null;   // /ByteRange  [start1 len1 start2 len2]
    public ?PdfArray $reference = null;   // /Reference  array of SignatureReference
    public ?PdfArray $changes = null;     // /Changes
    public ?PdfString $name = null;       // /Name
    public ?PdfString $m = null;          // /M          signing date (PDF date)
    public ?PdfString $location = null;   // /Location
    public ?PdfString $reason = null;     // /Reason
    public ?PdfString $contactInfo = null; // /ContactInfo
    public ?int $r = null;                // /R   handler revision
    public ?int $v = null;                // /V   dictionary version
    public ?PdfDictionary $propBuild = null; // /Prop_Build
    public ?int $propAuthTime = null;     // /Prop_AuthTime
    public ?PdfName $propAuthType = null; // /Prop_AuthType

    public function __construct(
        string $filter = 'Adobe.PPKLite',
        ?string $subFilter = 'adbe.pkcs7.detached',
        ?PdfString $contents = null
    ) {
        $this->filter = new PdfName($filter);
        if ($subFilter !== null) {
            $this->subFilter = new PdfName($subFilter);
        }
        // Default placeholder: 2048 bytes of zeros in hex form, typical for
        // signature byte-range precomputation.
        $this->contents = $contents ?? new PdfString(str_repeat("\x00", 2048), hex: true);
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
        if ($this->cert !== null) {
            $dict->set('Cert', $this->cert);
        }
        if ($this->byteRange !== null) {
            $dict->set('ByteRange', $this->byteRange);
        }
        if ($this->reference !== null) {
            $dict->set('Reference', $this->reference);
        }
        if ($this->changes !== null) {
            $dict->set('Changes', $this->changes);
        }
        if ($this->name !== null) {
            $dict->set('Name', $this->name);
        }
        if ($this->m !== null) {
            $dict->set('M', $this->m);
        }
        if ($this->location !== null) {
            $dict->set('Location', $this->location);
        }
        if ($this->reason !== null) {
            $dict->set('Reason', $this->reason);
        }
        if ($this->contactInfo !== null) {
            $dict->set('ContactInfo', $this->contactInfo);
        }
        if ($this->r !== null) {
            $dict->set('R', new PdfNumber($this->r));
        }
        if ($this->v !== null) {
            $dict->set('V', new PdfNumber($this->v));
        }
        if ($this->propBuild !== null) {
            $dict->set('Prop_Build', $this->propBuild);
        }
        if ($this->propAuthTime !== null) {
            $dict->set('Prop_AuthTime', new PdfNumber($this->propAuthTime));
        }
        if ($this->propAuthType !== null) {
            $dict->set('Prop_AuthType', $this->propAuthType);
        }
        return $dict->toPdf();
    }
}
