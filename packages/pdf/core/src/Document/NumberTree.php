<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfObject;

/**
 * General-purpose integer-keyed tree node (ISO 32000-2 Table 37).
 *
 * Number trees map integer keys to arbitrary values. Leaf nodes contain
 * /Nums arrays; intermediate nodes contain /Kids and /Limits.
 */
class NumberTree extends PdfObject
{
    public ?PdfArray $kids = null;    // /Kids - intermediate node children
    public ?PdfArray $nums = null;    // /Nums - leaf node key-value pairs
    public ?PdfArray $limits = null;  // /Limits - [min max] for intermediate nodes

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->kids !== null) {
            $dict->set('Kids', $this->kids);
        }
        if ($this->nums !== null) {
            $dict->set('Nums', $this->nums);
        }
        if ($this->limits !== null) {
            $dict->set('Limits', $this->limits);
        }

        return $dict->toPdf();
    }
}
