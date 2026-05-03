<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Set-OCG-state action (/S /SetOCGState) — ISO 32000-2 §12.6.4.12.
 * Toggles visibility of optional content groups.
 *
 * The /State array is a sequence of name + OCG reference pairs, e.g.:
 *   [/OFF 12 0 R 13 0 R /ON 14 0 R]
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class SetOCGStateAction extends Action
{
    public PdfArray $state;               // /State  required
    public ?bool $preserveRB = null;      // /PreserveRB

    public function __construct(PdfArray $state)
    {
        $this->state = $state;
    }

    public function getActionType(): string
    {
        return 'SetOCGState';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('State', $this->state);
        if ($this->preserveRB !== null) {
            $dict->set('PreserveRB', new PdfBoolean($this->preserveRB));
        }
        return $dict->toPdf();
    }
}
