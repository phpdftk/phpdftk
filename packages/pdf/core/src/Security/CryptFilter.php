<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Security;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Crypt filter dictionary — ISO 32000-2 §7.6.5, Table 25.
 *
 * Describes a single named crypt filter referenced from the /CF entry of
 * {@see EncryptDictionary}. Inline-serialized; not an indirect object.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class CryptFilter implements Serializable
{
    public ?PdfName $type = null;         // /Type        /CryptFilter
    public PdfName $cfm;                  // /CFM         None|V2|AESV2|AESV3
    public ?PdfName $authEvent = null;    // /AuthEvent   DocOpen|EFOpen
    public ?int $length = null;           // /Length      bytes (V2/AES)
    public ?PdfArray $recipients = null;  // /Recipients  public-key handler

    public function __construct(string $cfm = 'V2')
    {
        $this->cfm = new PdfName($cfm);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->type !== null) {
            $dict->set('Type', $this->type);
        }
        $dict->set('CFM', $this->cfm);
        if ($this->authEvent !== null) {
            $dict->set('AuthEvent', $this->authEvent);
        }
        if ($this->length !== null) {
            $dict->set('Length', new PdfNumber($this->length));
        }
        if ($this->recipients !== null) {
            $dict->set('Recipients', $this->recipients);
        }
        return $dict->toPdf();
    }
}
