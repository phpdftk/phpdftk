<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Halftone;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Dictionary-based halftone (HalftoneType 1) — ISO 32000-2 §10.6.4.
 *
 * Defines a single halftone screen with frequency, angle, and spot function.
 */
class HalftoneType1 extends PdfObject
{
    public const PDF_TYPE = 'Halftone';

    public ?float $frequency = null;                    // /Frequency — required
    public ?float $angle = null;                        // /Angle — required
    public Serializable|null $spotFunction = null;       // /SpotFunction — name or function
    public Serializable|null $transferFunction = null;   // /TransferFunction
    public ?bool $accurateScreens = null;               // /AccurateScreens

    public function toPdf(): string
    {
        $dict = new PdfDictionary([
            'Type' => new PdfName('Halftone'),
            'HalftoneType' => new PdfNumber(1),
        ]);

        if ($this->frequency !== null) {
            $dict->set('Frequency', new PdfNumber($this->frequency));
        }
        if ($this->angle !== null) {
            $dict->set('Angle', new PdfNumber($this->angle));
        }
        if ($this->spotFunction !== null) {
            $dict->set('SpotFunction', $this->spotFunction);
        }
        if ($this->transferFunction !== null) {
            $dict->set('TransferFunction', $this->transferFunction);
        }
        if ($this->accurateScreens !== null) {
            $dict->set('AccurateScreens', $this->accurateScreens ? new PdfName('true') : new PdfName('false'));
        }

        return $dict->toPdf();
    }
}
