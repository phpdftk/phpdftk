<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font\FontFile;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;

/**
 * Type 1 font program stream — ISO 32000-2 §9.9, Table 124.
 *
 * Embedded into a `FontDescriptor::$fontFile` reference. Carries the
 * classic three-segment Type 1 body: cleartext header, encrypted
 * portion, and trailer zeros — described by /Length1, /Length2,
 * /Length3 on the stream dictionary.
 */
class Type1FontFile extends PdfStream
{
    public int $length1;                    // /Length1 - ASCII header length
    public int $length2;                    // /Length2 - encrypted portion length
    public int $length3;                    // /Length3 - trailer zero-byte length
    public ?PdfReference $metadata = null;  // /Metadata

    public function __construct(
        string $bytes,
        int $length1,
        int $length2,
        int $length3
    ) {
        parent::__construct(new PdfDictionary(), $bytes);
        $this->length1 = $length1;
        $this->length2 = $length2;
        $this->length3 = $length3;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Length1', new PdfNumber($this->length1));
        $this->dictionary->set('Length2', new PdfNumber($this->length2));
        $this->dictionary->set('Length3', new PdfNumber($this->length3));
        if ($this->metadata !== null) {
            $this->dictionary->set('Metadata', $this->metadata);
        }
        return parent::toPdf();
    }
}
