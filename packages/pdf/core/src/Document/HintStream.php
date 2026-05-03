<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfStream;

/**
 * Primary hint stream — ISO 32000-2 §F.3.1.
 *
 * A stream whose dictionary contains hint-table offsets (P, S, T, O,
 * A, E, V, I, C, L, R, B) pointing into the body of the stream. Used
 * by linearized PDFs to speed up progressive rendering.
 *
 * Object-model only; not emitted by `PdfWriter`.
 */
class HintStream extends PdfStream
{
    public ?int $p = null;  // /P pages hint table offset
    public ?int $s = null;  // /S shared-object hint table offset
    public ?int $t = null;  // /T thumbnail hint table offset
    public ?int $o = null;  // /O outline
    public ?int $a = null;  // /A thread
    public ?int $e = null;  // /E named-destination
    public ?int $v = null;  // /V interactive-form
    public ?int $i = null;  // /I info
    public ?int $c = null;  // /C logical-structure
    public ?int $l = null;  // /L page label
    public ?int $r = null;  // /R renditions
    public ?int $b = null;  // /B embedded files

    public function __construct(string $data = '')
    {
        parent::__construct(new PdfDictionary(), $data);
    }

    public function toPdf(): string
    {
        foreach ([
            'P' => $this->p, 'S' => $this->s, 'T' => $this->t,
            'O' => $this->o, 'A' => $this->a, 'E' => $this->e,
            'V' => $this->v, 'I' => $this->i, 'C' => $this->c,
            'L' => $this->l, 'R' => $this->r, 'B' => $this->b,
        ] as $key => $value) {
            if ($value !== null) {
                $this->dictionary->set($key, new PdfNumber($value));
            }
        }
        return parent::toPdf();
    }
}
