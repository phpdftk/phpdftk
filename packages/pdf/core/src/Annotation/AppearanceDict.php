<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Appearance dictionary (ISO 32000-2 Table 170).
 *
 * Defines the visual appearance of an annotation in various states.
 * Assigned to the /AP entry of annotation dictionaries.
 *
 * Example:
 *   $ap = new AppearanceDict();
 *   $ap->n = new PdfReference($normalAppearance->objectNumber);
 *   $annotation->ap = $ap;
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class AppearanceDict implements Serializable
{
    public PdfReference|PdfDictionary|null $n = null;  // /N - normal appearance
    public PdfReference|PdfDictionary|null $r = null;  // /R - rollover appearance
    public PdfReference|PdfDictionary|null $d = null;  // /D - down appearance

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->n !== null) {
            $dict->set('N', $this->n);
        }
        if ($this->r !== null) {
            $dict->set('R', $this->r);
        }
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }

        return $dict->toPdf();
    }
}
