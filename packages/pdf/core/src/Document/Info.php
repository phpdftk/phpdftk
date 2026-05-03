<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfString;

/**
 * PDF Document Information Dictionary.
 * Not a /Type object; embedded in the trailer's /Info entry.
 */
class Info extends PdfObject
{
    public ?PdfString $title = null;        // /Title
    public ?PdfString $author = null;       // /Author
    public ?PdfString $subject = null;      // /Subject
    public ?PdfString $keywords = null;     // /Keywords
    public ?PdfString $creator = null;      // /Creator
    public ?PdfString $producer = null;     // /Producer
    public ?PdfString $creationDate = null; // /CreationDate
    public ?PdfString $modDate = null;      // /ModDate
    public ?PdfName $trapped = null;        // /Trapped

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->title !== null) {
            $dict->set('Title', $this->title);
        }
        if ($this->author !== null) {
            $dict->set('Author', $this->author);
        }
        if ($this->subject !== null) {
            $dict->set('Subject', $this->subject);
        }
        if ($this->keywords !== null) {
            $dict->set('Keywords', $this->keywords);
        }
        if ($this->creator !== null) {
            $dict->set('Creator', $this->creator);
        }
        if ($this->producer !== null) {
            $dict->set('Producer', $this->producer);
        }
        if ($this->creationDate !== null) {
            $dict->set('CreationDate', $this->creationDate);
        }
        if ($this->modDate !== null) {
            $dict->set('ModDate', $this->modDate);
        }
        if ($this->trapped !== null) {
            $dict->set('Trapped', $this->trapped);
        }

        return $dict->toPdf();
    }
}
