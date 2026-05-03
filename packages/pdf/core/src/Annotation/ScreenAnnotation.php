<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Screen annotation (/Subtype /Screen).
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class ScreenAnnotation extends Annotation
{
    public ?PdfString $t = null;       // /T - title
    public ?Serializable $mk = null;   // /MK - appearance characteristics
    public ?PdfReference $a = null;    // /A - action
    public ?PdfReference $aa = null;   // /AA - additional actions

    public function getSubtype(): string
    {
        return 'Screen';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->t !== null) {
            $dict->set('T', $this->t);
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

        return $dict->toPdf();
    }
}
