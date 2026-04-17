<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Signature reference dictionary (/Type /SigRef) — ISO 32000-2 §12.8.1,
 * Table 253. Referenced by /Reference on a SignatureValue; each entry
 * names a transform method plus its params.
 */
class SignatureReference extends PdfObject
{
    public const PDF_TYPE = 'SigRef';

    public PdfName $transformMethod;                                 // /TransformMethod
    public TransformParams|PdfReference|PdfDictionary|null $transformParams = null; // /TransformParams
    public ?PdfReference $data = null;                               // /Data
    public ?PdfName $digestMethod = null;                            // /DigestMethod  MD5|SHA1|SHA256|SHA384|SHA512|RIPEMD160

    public function __construct(string $transformMethod)
    {
        $this->transformMethod = new PdfName($transformMethod);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('TransformMethod', $this->transformMethod);
        if ($this->transformParams !== null) {
            $dict->set('TransformParams', $this->transformParams);
        }
        if ($this->data !== null) {
            $dict->set('Data', $this->data);
        }
        if ($this->digestMethod !== null) {
            $dict->set('DigestMethod', $this->digestMethod);
        }
        return $dict->toPdf();
    }
}
