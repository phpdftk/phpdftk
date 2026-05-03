<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;

/**
 * Link annotation (/Subtype /Link).
 */
class LinkAnnotation extends Annotation
{
    public ?PdfReference $dest = null;      // /Dest
    public ?PdfDictionary $a = null;        // /A - action
    public ?PdfDictionary $pa = null;       // /PA
    public ?PdfArray $quadPoints = null;    // /QuadPoints
    public ?PdfName $h = null;              // /H - highlight mode

    public function getSubtype(): string
    {
        return 'Link';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->dest !== null) {
            $dict->set('Dest', $this->dest);
        }
        if ($this->a !== null) {
            $dict->set('A', $this->a);
        }
        if ($this->pa !== null) {
            $dict->set('PA', $this->pa);
        }
        if ($this->quadPoints !== null) {
            $dict->set('QuadPoints', $this->quadPoints);
        }
        if ($this->h !== null) {
            $dict->set('H', $this->h);
        }

        return $dict->toPdf();
    }
}
