<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Movie annotation (/Subtype /Movie).
 */
class MovieAnnotation extends Annotation
{
    public ?PdfString $t = null;          // /T - title
    public ?PdfReference $movie = null;   // /Movie - movie dict
    public ?PdfDictionary $a = null;      // /A - activation dict

    public function getSubtype(): string
    {
        return 'Movie';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->t !== null) {
            $dict->set('T', $this->t);
        }
        if ($this->movie !== null) {
            $dict->set('Movie', $this->movie);
        }
        if ($this->a !== null) {
            $dict->set('A', $this->a);
        }

        return $dict->toPdf();
    }
}
