<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\ThreeD;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * 3D node dictionary (/Type /3DNode) — ISO 32000-2 §13.6.4.5.
 *
 * Overrides the transform / visibility / rendering of a named node
 * inside a 3D stream. Referenced from `ThreeDView::$na`.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class ThreeDNode extends PdfObject
{
    public const PDF_TYPE = '3DNode';

    public PdfString $n;                // /N - name (required)
    public ?PdfArray $m = null;         // /M - transform matrix
    public ?bool $v = null;             // /V - visible
    public ?float $o = null;            // /O - opacity
    public ?PdfDictionary $rm = null;   // /RM - render mode override

    public function __construct(string $name)
    {
        $this->n = new PdfString($name);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('N', $this->n);
        if ($this->m !== null) {
            $dict->set('M', $this->m);
        }
        if ($this->v !== null) {
            $dict->set('V', new PdfBoolean($this->v));
        }
        if ($this->o !== null) {
            $dict->set('O', new \Phpdftk\Pdf\Core\PdfNumber($this->o));
        }
        if ($this->rm !== null) {
            $dict->set('RM', $this->rm);
        }
        return $dict->toPdf();
    }
}
