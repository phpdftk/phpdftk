<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Document security store — ISO 32000-2 §12.8.4.3.
 *
 * Holds the validation-related information (VRI) used by PAdES LTV
 * signatures: revocation checks (OCSPs, CRLs) and the certificate chain
 * required to independently verify a signature long after signing time.
 *
 * Referenced from `Catalog::$dss`.
 */
#[RequiresPdfVersion(PdfVersion::V2_0)]
class DSS extends PdfObject
{
    public ?PdfArray $certs = null;       // /Certs  - array of cert streams
    public ?PdfArray $ocsps = null;       // /OCSPs  - array of OCSP responses
    public ?PdfArray $crls = null;        // /CRLs   - array of CRL streams
    public ?PdfDictionary $vri = null;    // /VRI    - validation related info

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->certs !== null) {
            $dict->set('Certs', $this->certs);
        }
        if ($this->ocsps !== null) {
            $dict->set('OCSPs', $this->ocsps);
        }
        if ($this->crls !== null) {
            $dict->set('CRLs', $this->crls);
        }
        if ($this->vri !== null) {
            $dict->set('VRI', $this->vri);
        }
        return $dict->toPdf();
    }
}
