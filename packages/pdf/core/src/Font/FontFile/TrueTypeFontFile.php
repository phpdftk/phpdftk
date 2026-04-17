<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font\FontFile;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;

/**
 * TrueType font program stream — ISO 32000-2 §9.9, Table 124.
 *
 * Embedded into `FontDescriptor::$fontFile2`. Carries the full TTF file
 * bytes; /Length1 is the total length of the unfiltered stream data.
 */
class TrueTypeFontFile extends PdfStream
{
    public int $length1;                    // /Length1 - uncompressed TTF length
    public ?PdfReference $metadata = null;  // /Metadata

    public function __construct(string $bytes)
    {
        parent::__construct(new PdfDictionary(), $bytes);
        $this->length1 = strlen($bytes);
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Length1', new PdfNumber($this->length1));
        if ($this->metadata !== null) {
            $this->dictionary->set('Metadata', $this->metadata);
        }
        return parent::toPdf();
    }
}
