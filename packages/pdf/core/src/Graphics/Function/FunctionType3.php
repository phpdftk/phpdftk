<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Function;

use Phpdftk\Pdf\Core\PdfArray;

/**
 * Stitching function (FunctionType 3) — ISO 32000-2 §7.10.4.
 *
 * Combines `k` 1-input functions into a single function by stitching them
 * along the domain. `Bounds` has `k - 1` entries that partition `Domain`;
 * `Encode` has `2 * k` entries mapping each sub-domain into its function's
 * domain.
 */
class FunctionType3 extends Func
{
    public PdfArray $functions;   // /Functions - k 1-D sub-functions
    public PdfArray $bounds;      // /Bounds
    public PdfArray $encode;      // /Encode

    public function __construct(
        PdfArray $domain,
        PdfArray $functions,
        PdfArray $bounds,
        PdfArray $encode
    ) {
        $this->domain = $domain;
        $this->functions = $functions;
        $this->bounds = $bounds;
        $this->encode = $encode;
    }

    public function getFunctionType(): int
    {
        return 3;
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('Functions', $this->functions);
        $dict->set('Bounds', $this->bounds);
        $dict->set('Encode', $this->encode);
        return $dict->toPdf();
    }
}
