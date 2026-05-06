<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Function;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;

/**
 * Exponential interpolation function (FunctionType 2) — ISO 32000-2 §7.10.3.
 *
 * Defines a function f(x) = C0 + x^N * (C1 - C0) over a 1-D domain.
 * Used most commonly to define solid-to-solid color ramps for shadings.
 */
class FunctionType2 extends Func
{
    public PdfArray $c0;   // /C0 - output when x = 0 (default [0])
    public PdfArray $c1;   // /C1 - output when x = 1 (default [1])
    public float $n;       // /N  - interpolation exponent

    public function __construct(
        PdfArray $domain,
        PdfArray $c0,
        PdfArray $c1,
        float $n = 1.0,
    ) {
        $this->domain = $domain;
        $this->c0 = $c0;
        $this->c1 = $c1;
        $this->n = $n;
    }

    public function getFunctionType(): int
    {
        return 2;
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('C0', $this->c0);
        $dict->set('C1', $this->c1);
        $dict->set('N', new PdfNumber($this->n));
        return $dict->toPdf();
    }
}
