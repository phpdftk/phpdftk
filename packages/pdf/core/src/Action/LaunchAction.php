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
 * Launch action (/S /Launch) — ISO 32000-2 §12.6.4.5.
 * Launches an application or opens/prints a document.
 */
#[RequiresPdfVersion(PdfVersion::V1_1)]
class LaunchAction extends Action
{
    public FileSpec|PdfReference|null $f = null;   // /F
    public ?PdfDictionary $win = null;             // /Win
    public ?PdfDictionary $mac = null;             // /Mac
    public ?PdfDictionary $unix = null;            // /Unix
    public ?bool $newWindow = null;                // /NewWindow

    public function getActionType(): string
    {
        return 'Launch';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->f !== null) {
            $dict->set('F', $this->f);
        }
        if ($this->win !== null) {
            $dict->set('Win', $this->win);
        }
        if ($this->mac !== null) {
            $dict->set('Mac', $this->mac);
        }
        if ($this->unix !== null) {
            $dict->set('Unix', $this->unix);
        }
        if ($this->newWindow !== null) {
            $dict->set('NewWindow', new PdfBoolean($this->newWindow));
        }
        return $dict->toPdf();
    }
}
