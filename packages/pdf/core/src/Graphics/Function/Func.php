<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Function;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Abstract base for PDF function objects (ISO 32000-2 §7.10).
 *
 * Named `Func` rather than `Function` since `function` is a PHP keyword.
 * Subclasses are FunctionType0 (sampled), Type2 (exponential),
 * Type3 (stitching), and Type4 (PostScript calculator).
 *
 * Common entries across all function types: FunctionType, Domain, Range.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
abstract class Func extends PdfObject
{
    public PdfArray $domain;        // /Domain - required
    public ?PdfArray $range = null; // /Range  - required for types 0 and 4

    abstract public function getFunctionType(): int;

    protected function baseDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('FunctionType', new PdfNumber($this->getFunctionType()));
        $dict->set('Domain', $this->domain);
        if ($this->range !== null) {
            $dict->set('Range', $this->range);
        }
        return $dict;
    }
}
