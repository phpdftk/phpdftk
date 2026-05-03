<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\Document\TransitionDict;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Transition action (/S /Trans) — ISO 32000-2 §12.6.4.14.
 * Controls the visual transition when jumping to a destination.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class TransAction extends Action
{
    public TransitionDict|PdfReference $trans;   // /Trans

    public function __construct(TransitionDict|PdfReference $trans)
    {
        $this->trans = $trans;
    }

    public function getActionType(): string
    {
        return 'Trans';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('Trans', $this->trans);
        return $dict->toPdf();
    }
}
