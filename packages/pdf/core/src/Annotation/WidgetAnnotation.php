<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Widget annotation (/Subtype /Widget).
 * Used to represent interactive form fields on a page.
 */
class WidgetAnnotation extends Annotation
{
    public ?PdfName $h = null;           // /H - highlight mode
    public ?PdfReference $mk = null;     // /MK - appearance characteristics
    public ?PdfReference $a = null;      // /A - action
    public ?PdfReference $aa = null;     // /AA - additional actions
    public ?PdfReference $parent = null; // /Parent

    public function getSubtype(): string
    {
        return 'Widget';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->h !== null) {
            $dict->set('H', $this->h);
        }
        if ($this->mk !== null) {
            $dict->set('MK', $this->mk);
        }
        if ($this->a !== null) {
            $dict->set('A', $this->a);
        }
        if ($this->aa !== null) {
            $dict->set('AA', $this->aa);
        }
        if ($this->parent !== null) {
            $dict->set('Parent', $this->parent);
        }

        return $dict->toPdf();
    }
}
