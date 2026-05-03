<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\DeprecatedPdfFeature;

/**
 * Movie annotation (/Subtype /Movie).
 */
#[RequiresPdfVersion(PdfVersion::V1_4)]
#[DeprecatedPdfFeature(since: '2.0', replacement: 'ScreenAnnotation', removedIn: '2.0')]
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
