<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Security;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Public-key recipient dictionary — ISO 32000-2 §7.6.5.3, Table 27.
 *
 * A single entry in the /Recipients array of a public-key crypt filter.
 * Carries the PKCS#7 envelope that encrypts the file encryption key for
 * one recipient plus that recipient's access permissions.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class PublicKeyRecipient implements Serializable
{
    public PdfString $pkcs7;            // the envelope (byte string)
    public ?int $p = null;              // /P permissions
    public ?PdfArray $recipient = null; // optional /Recipient identity

    public function __construct(PdfString $pkcs7)
    {
        $this->pkcs7 = $pkcs7;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('PKCS7', $this->pkcs7);
        if ($this->p !== null) {
            $dict->set('P', new PdfNumber($this->p));
        }
        if ($this->recipient !== null) {
            $dict->set('Recipient', $this->recipient);
        }
        return $dict->toPdf();
    }
}
