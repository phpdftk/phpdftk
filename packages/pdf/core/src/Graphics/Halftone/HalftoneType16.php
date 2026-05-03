<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Halftone;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Threshold array halftone stream (HalftoneType 16) — ISO 32000-2 §10.6.5.
 *
 * Uses 16-bit threshold values (2 bytes per cell).
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
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
