<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Halftone;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Threshold array halftone stream (HalftoneType 16) — ISO 32000-2 §10.6.5.
 *
 * Uses 16-bit threshold values (2 bytes per cell).
 */
class HalftoneType16 extends PdfStream
{
    public const PDF_TYPE = 'Halftone';

    public int $width = 0;                              // /Width
    public int $height = 0;                             // /Height
    public Serializable|null $transferFunction = null;   // /TransferFunction

    public function __construct(int $width = 0, int $height = 0, string $data = '')
    {
        parent::__construct(new PdfDictionary(), $data);
        $this->width = $width;
        $this->height = $height;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName('Halftone'));
        $this->dictionary->set('HalftoneType', new PdfNumber(16));
        $this->dictionary->set('Width', new PdfNumber($this->width));
        $this->dictionary->set('Height', new PdfNumber($this->height));

        if ($this->transferFunction !== null) {
            $this->dictionary->set('TransferFunction', $this->transferFunction);
        }

        return parent::toPdf();
    }
}
