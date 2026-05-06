<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Function;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfStream;

/**
 * Sampled function (FunctionType 0) — ISO 32000-2 §7.10.2.
 *
 * Interpolates from a table of sample values. Must be a stream: the body
 * holds BitsPerSample-wide binary samples packed big-endian. Required
 * entries: Size, BitsPerSample, Domain, Range (plus Order, Encode, Decode).
 */
class FunctionType0 extends PdfStream
{
    public PdfArray $domain;                 // /Domain
    public PdfArray $range;                  // /Range
    public PdfArray $size;                   // /Size       N input components
    public int $bitsPerSample;               // /BitsPerSample (1,2,4,8,12,16,24,32)
    public ?int $order = null;               // /Order      1 or 3
    public ?PdfArray $encode = null;         // /Encode
    public ?PdfArray $decode = null;         // /Decode

    public function __construct(
        PdfArray $domain,
        PdfArray $range,
        PdfArray $size,
        int $bitsPerSample,
        string $samples = '',
    ) {
        parent::__construct(new PdfDictionary(), $samples);
        $this->domain = $domain;
        $this->range = $range;
        $this->size = $size;
        $this->bitsPerSample = $bitsPerSample;
    }

    public function getFunctionType(): int
    {
        return 0;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('FunctionType', new PdfNumber(0));
        $this->dictionary->set('Domain', $this->domain);
        $this->dictionary->set('Range', $this->range);
        $this->dictionary->set('Size', $this->size);
        $this->dictionary->set('BitsPerSample', new PdfNumber($this->bitsPerSample));
        if ($this->order !== null) {
            $this->dictionary->set('Order', new PdfNumber($this->order));
        }
        if ($this->encode !== null) {
            $this->dictionary->set('Encode', $this->encode);
        }
        if ($this->decode !== null) {
            $this->dictionary->set('Decode', $this->decode);
        }
        return parent::toPdf();
    }
}
