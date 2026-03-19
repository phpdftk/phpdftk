<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * PDF Outlines dictionary (/Type /Outlines).
 *
 * The root of the document outline (bookmarks) tree.
 * Referenced from the Catalog /Outlines field.
 */
class Outline extends PdfObject
{
    public const PDF_TYPE = 'Outlines';

    public ?PdfReference $first = null;  // /First - first top-level outline item
    public ?PdfReference $last  = null;  // /Last  - last top-level outline item
    public int $count = 0;               // /Count - total visible outline items

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->first !== null) {
            $dict->set('First', $this->first);
        }
        if ($this->last !== null) {
            $dict->set('Last', $this->last);
        }
        if ($this->count !== 0) {
            $dict->set('Count', new PdfNumber($this->count));
        }

        return $dict->toPdf();
    }
}
