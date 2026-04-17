<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Function;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfStream;

/**
 * PostScript calculator function (FunctionType 4) — ISO 32000-2 §7.10.5.
 *
 * Evaluates a small subset of PostScript embedded in the stream body.
 * Required entries: Domain, Range. The stream body must begin with `{`
 * and end with `}`, e.g. `{ 1 exch sub }`.
 */
class FunctionType4 extends PdfStream
{
    public PdfArray $domain;    // /Domain
    public PdfArray $range;     // /Range

    public function __construct(PdfArray $domain, PdfArray $range, string $postScript)
    {
        parent::__construct(new PdfDictionary(), $postScript);
        $this->domain = $domain;
        $this->range = $range;
    }

    public function getFunctionType(): int
    {
        return 4;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('FunctionType', new PdfNumber(4));
        $this->dictionary->set('Domain', $this->domain);
        $this->dictionary->set('Range', $this->range);
        return parent::toPdf();
    }
}
