<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Halftone;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Composite halftone (HalftoneType 5) — ISO 32000-2 §10.6.5.
 *
 * Contains one entry per colorant (e.g. /Cyan, /Magenta, etc.),
 * each referencing a single-component halftone, plus a /Default entry.
 */
class HalftoneType5 extends PdfObject
{
    public const PDF_TYPE = 'Halftone';

    public Serializable|null $default = null;  // /Default — required
    public ?PdfDictionary $colorants = null;    // Additional colorant entries

    public function toPdf(): string
    {
        $dict = new PdfDictionary([
            'Type' => new PdfName('Halftone'),
            'HalftoneType' => new PdfNumber(5),
        ]);

        if ($this->default !== null) {
            $dict->set('Default', $this->default);
        }

        // Merge colorant entries into the dictionary
        if ($this->colorants !== null) {
            foreach ($this->colorants->entries as $key => $value) {
                $dict->set($key, $value);
            }
        }

        return $dict->toPdf();
    }
}
