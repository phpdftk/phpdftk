<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Multimedia;

use ApprLabs\Pdf\Core\FileSpec\FileSpec;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Movie dictionary — ISO 32000-2 §13.4 (deprecated in PDF 2.0 in favor
 * of RichMedia, but still part of the spec and referenced by
 * MovieAnnotation).
 */
class Movie extends PdfObject
{
    public FileSpec|PdfReference $f;         // /F  file spec (required)
    public ?PdfArray $aspect = null;         // /Aspect  [width height]
    public ?float $rotate = null;            // /Rotate  degrees
    public bool|PdfReference|null $poster = null; // /Poster

    public function __construct(FileSpec|PdfReference $f)
    {
        $this->f = $f;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('F', $this->f);
        if ($this->aspect !== null) {
            $dict->set('Aspect', $this->aspect);
        }
        if ($this->rotate !== null) {
            $dict->set('Rotate', new PdfNumber($this->rotate));
        }
        if ($this->poster !== null) {
            if (is_bool($this->poster)) {
                $dict->set('Poster', new PdfBoolean($this->poster));
            } else {
                $dict->set('Poster', $this->poster);
            }
        }
        return $dict->toPdf();
    }
}
