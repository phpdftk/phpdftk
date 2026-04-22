<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Go-to-3D-view action (/S /GoTo3DView) — ISO 32000-2 §12.6.4.15.
 * Selects a 3D view for a 3D annotation.
 */
#[RequiresPdfVersion(PdfVersion::V1_6)]
class GoTo3DViewAction extends Action
{
    public PdfReference $ta;   // /TA  target annotation (required)
    public mixed $v = null;    // /V   view specifier (name or dict)

    public function __construct(PdfReference $ta)
    {
        $this->ta = $ta;
    }

    public function getActionType(): string
    {
        return 'GoTo3DView';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('TA', $this->ta);
        if ($this->v !== null) {
            $dict->set('V', $this->v);
        }
        return $dict->toPdf();
    }
}
