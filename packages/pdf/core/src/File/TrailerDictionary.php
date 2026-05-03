<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\File;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Trailer dictionary — ISO 32000-2 §7.5.5, Table 17.
 *
 * Carries the bookkeeping entries that follow the classic `trailer`
 * keyword at the end of a PDF file: the total object count (/Size), the
 * catalog reference (/Root), the optional info dict, file ID, previous
 * xref offset (for incremental updates), and encrypt dict reference.
 *
 * Used by {@see PdfFileWriter} to emit the trailer section of a
 * generated PDF.
 */
class TrailerDictionary implements Serializable
{
    public int $size = 0;                       // /Size
    public PdfReference $root;                  // /Root - required
    public ?PdfReference $info = null;          // /Info
    public ?PdfReference $encrypt = null;       // /Encrypt
    public ?PdfArray $id = null;                // /ID - 2-element byte-string array
    public ?int $prev = null;                   // /Prev - byte offset of previous xref

    public function __construct(PdfReference $root)
    {
        $this->root = $root;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Size', new PdfNumber($this->size));
        $dict->set('Root', $this->root);
        if ($this->info !== null) {
            $dict->set('Info', $this->info);
        }
        if ($this->encrypt !== null) {
            $dict->set('Encrypt', $this->encrypt);
        }
        if ($this->id !== null) {
            $dict->set('ID', $this->id);
        }
        if ($this->prev !== null) {
            $dict->set('Prev', new PdfNumber($this->prev));
        }
        return $dict->toPdf();
    }
}
