<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Halftone;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Composite halftone (HalftoneType 5) — ISO 32000-2 §10.6.5.
 *
 * Contains one entry per colorant (e.g. /Cyan, /Magenta, etc.),
 * each referencing a single-component halftone, plus a /Default entry.
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
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
