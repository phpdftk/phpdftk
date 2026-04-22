<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Page Label dictionary (/Type /PageLabel).
 *
 * Defines the numbering scheme for a range of pages.
 * Page label ranges are stored as a number tree in /PageLabels in the Catalog;
 * each key is the zero-based page index where the range begins.
 *
 * Numbering styles (/S):
 *   D  - Decimal Arabic numerals (1, 2, 3, …)
 *   r  - Lowercase Roman numerals (i, ii, iii, …)
 *   R  - Uppercase Roman numerals (I, II, III, …)
 *   a  - Lowercase letters (a, b, …, z, aa, …)
 *   A  - Uppercase letters (A, B, …, Z, AA, …)
 *   (omit /S to use prefix only, with no numeric part)
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class PageLabel extends PdfObject
{
    public const PDF_TYPE = 'PageLabel';

    public ?PdfName $s   = null;  // /S  - numbering style
    public ?PdfString $p = null;  // /P  - label prefix
    public int $st       = 1;     // /St - starting value (default 1)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->s !== null) {
            $dict->set('S', $this->s);
        }
        if ($this->p !== null) {
            $dict->set('P', $this->p);
        }
        if ($this->st !== 1) {
            $dict->set('St', new PdfNumber($this->st));
        }

        return $dict->toPdf();
    }
}
