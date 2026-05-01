<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Filter;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Crypt filter /DecodeParms — ISO 32000-2 §7.4.10, Table 14.
 *
 * Selects which named crypt filter from the document's /CF dictionary
 * is used to decrypt a specific stream.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class CryptFilterDecodeParams implements Serializable
{
    public ?PdfName $type = null;    // /Type /CryptFilterDecodeParms
    public ?PdfName $name = null;    // /Name

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->type !== null) {
            $dict->set('Type', $this->type);
        }
        if ($this->name !== null) {
            $dict->set('Name', $this->name);
        }
        return $dict->toPdf();
    }
}
