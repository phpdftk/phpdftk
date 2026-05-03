<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\FileSpec;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Embedded file parameters (`/Params` entry of an EmbeddedFile stream) —
 * ISO 32000-2 §7.11.4 Table 43.
 *
 * Appears inline inside an EmbeddedFile stream's dictionary; not a
 * standalone indirect object.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class EmbeddedFileParams implements Serializable
{
    public ?int $size = null;                 // /Size      - file size
    public ?PdfString $creationDate = null;   // /CreationDate
    public ?PdfString $modDate = null;        // /ModDate
    public ?PdfString $mac = null;            // /Mac  - legacy
    public ?PdfString $checkSum = null;       // /CheckSum (MD5, hex-encoded)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->size !== null) {
            $dict->set('Size', new PdfNumber($this->size));
        }
        if ($this->creationDate !== null) {
            $dict->set('CreationDate', $this->creationDate);
        }
        if ($this->modDate !== null) {
            $dict->set('ModDate', $this->modDate);
        }
        if ($this->mac !== null) {
            $dict->set('Mac', $this->mac);
        }
        if ($this->checkSum !== null) {
            $dict->set('CheckSum', $this->checkSum);
        }
        return $dict->toPdf();
    }
}
