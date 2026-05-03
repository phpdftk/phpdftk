<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Reset-form action (/S /ResetForm) — ISO 32000-2 §12.7.5.3.
 * Resets form field values to their defaults.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class ResetFormAction extends Action
{
    public ?PdfArray $fields = null;   // /Fields  (default: all fields)
    public int $flags = 0;             // /Flags   (bit 1 = Include/Exclude)

    public function getActionType(): string
    {
        return 'ResetForm';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->fields !== null) {
            $dict->set('Fields', $this->fields);
        }
        if ($this->flags !== 0) {
            $dict->set('Flags', new PdfNumber($this->flags));
        }
        return $dict->toPdf();
    }
}
