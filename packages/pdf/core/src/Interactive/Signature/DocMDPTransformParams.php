<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Signature;

use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * DocMDP transform parameters — ISO 32000-2 §12.8.2.2, Table 254.
 *
 * Used with a DocMDP (certification) signature to restrict subsequent
 * modifications. /P controls what's allowed:
 *   1 = no changes
 *   2 = form filling, signing, comments allowed
 *   3 = same as 2 plus form field creation/deletion
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class DocMDPTransformParams extends TransformParams
{
    public int $p = 2;             // /P  permissions (1, 2, 3)
    public ?PdfName $v = null;     // /V  version (default 1.2)

    public function __construct(int $p = 2)
    {
        $this->p = $p;
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('P', new PdfNumber($this->p));
        if ($this->v !== null) {
            $dict->set('V', $this->v);
        }
        return $dict->toPdf();
    }
}
