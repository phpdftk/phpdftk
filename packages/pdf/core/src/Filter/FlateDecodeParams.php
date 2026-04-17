<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Filter;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Serializable;

/**
 * FlateDecode / LZWDecode predictor parameters — ISO 32000-2 §7.4.4.3,
 * Table 8. Inline-serialized into a stream's /DecodeParms.
 */
class FlateDecodeParams implements Serializable
{
    public ?int $predictor = null;          // /Predictor
    public ?int $columns = null;            // /Columns
    public ?int $colors = null;             // /Colors
    public ?int $bitsPerComponent = null;   // /BitsPerComponent
    public ?int $earlyChange = null;        // /EarlyChange (LZW only)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->predictor !== null) {
            $dict->set('Predictor', new PdfNumber($this->predictor));
        }
        if ($this->columns !== null) {
            $dict->set('Columns', new PdfNumber($this->columns));
        }
        if ($this->colors !== null) {
            $dict->set('Colors', new PdfNumber($this->colors));
        }
        if ($this->bitsPerComponent !== null) {
            $dict->set('BitsPerComponent', new PdfNumber($this->bitsPerComponent));
        }
        if ($this->earlyChange !== null) {
            $dict->set('EarlyChange', new PdfNumber($this->earlyChange));
        }
        return $dict->toPdf();
    }
}
