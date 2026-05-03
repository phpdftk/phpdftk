<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Thread action (/S /Thread) — ISO 32000-2 §12.6.4.6.
 * Jumps to a specific article thread bead.
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class ThreadAction extends Action
{
    public FileSpec|PdfReference|null $f = null;   // /F  optional remote file
    public mixed $d = null;                        // /D  thread (indirect/name/int)
    public mixed $b = null;                        // /B  bead (indirect/int)

    public function getActionType(): string
    {
        return 'Thread';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->f !== null) {
            $dict->set('F', $this->f);
        }
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }
        if ($this->b !== null) {
            $dict->set('B', $this->b);
        }
        return $dict->toPdf();
    }
}
