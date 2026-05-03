<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Go-to-embedded action (/S /GoToE) — ISO 32000-2 §12.6.4.4.
 * Navigates to a destination inside an embedded PDF.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class GoToEAction extends Action
{
    public FileSpec|PdfReference|null $f = null;   // /F  containing file
    public mixed $d;                               // /D  destination (required)
    public ?bool $newWindow = null;                // /NewWindow
    public ?PdfDictionary $t = null;               // /T  target specifier

    public function __construct(mixed $dest)
    {
        $this->d = $dest;
    }

    public function getActionType(): string
    {
        return 'GoToE';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->f !== null) {
            $dict->set('F', $this->f);
        }
        $dict->set('D', $this->d);
        if ($this->newWindow !== null) {
            $dict->set('NewWindow', new PdfBoolean($this->newWindow));
        }
        if ($this->t !== null) {
            $dict->set('T', $this->t);
        }
        return $dict->toPdf();
    }
}
