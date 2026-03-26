<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfObject;

/**
 * General-purpose name-keyed tree node (ISO 32000-2 Table 36).
 *
 * Name trees map string keys to arbitrary values. Leaf nodes contain
 * /Names arrays; intermediate nodes contain /Kids and /Limits.
 */
class NameTree extends PdfObject
{
    public ?PdfArray $kids = null;    // /Kids - intermediate node children
    public ?PdfArray $names = null;   // /Names - leaf node key-value pairs
    public ?PdfArray $limits = null;  // /Limits - [min max] for intermediate nodes

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->kids !== null) {
            $dict->set('Kids', $this->kids);
        }
        if ($this->names !== null) {
            $dict->set('Names', $this->names);
        }
        if ($this->limits !== null) {
            $dict->set('Limits', $this->limits);
        }

        return $dict->toPdf();
    }
}
