<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Sound action (/S /Sound) — ISO 32000-2 §12.6.4.8.
 * Plays a sound stream.
 */
class SoundAction extends Action
{
    public PdfReference $sound;           // /Sound - required, stream
    public ?float $volume = null;         // /Volume  [-1.0 .. 1.0]
    public ?bool $synchronous = null;     // /Synchronous
    public ?bool $repeat = null;          // /Repeat
    public ?bool $mix = null;             // /Mix

    public function __construct(PdfReference $sound)
    {
        $this->sound = $sound;
    }

    public function getActionType(): string
    {
        return 'Sound';
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('Sound', $this->sound);
        if ($this->volume !== null) {
            $dict->set('Volume', new PdfNumber($this->volume));
        }
        if ($this->synchronous !== null) {
            $dict->set('Synchronous', new PdfBoolean($this->synchronous));
        }
        if ($this->repeat !== null) {
            $dict->set('Repeat', new PdfBoolean($this->repeat));
        }
        if ($this->mix !== null) {
            $dict->set('Mix', new PdfBoolean($this->mix));
        }
        return $dict->toPdf();
    }
}
