<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Filter;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

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
