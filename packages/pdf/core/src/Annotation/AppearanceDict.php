<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Serializable;

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
