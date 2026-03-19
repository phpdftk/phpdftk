<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\Serializable;

/**
 * PDF Page Transition dictionary (/Type /Trans).
 *
 * Defines the visual transition effect when advancing to a page.
 * Assigned to Page::$transition; also used as the document-level
 * /Trans entry via PdfWriter::setTransition().
 *
 * Style (/S) values:
 *   Split, Blinds, Box, Wipe, Dissolve, Glitter, R,
 *   Fly, Push, Cover, Uncover, Fade
 *
 * Example:
 *   $t = new TransitionDict();
 *   $t->s  = new PdfName('Dissolve');
 *   $t->d  = new PdfNumber(1.5);
 *   $page->transition = $t;
 */
class TransitionDict implements Serializable
{
    public ?PdfName $s    = null;  // /S  - transition style
    public ?PdfNumber $d  = null;  // /D  - duration in seconds (default 1)
    public ?PdfName $dm   = null;  // /Dm - dimension: H (horizontal) or V (vertical)
    public ?PdfName $m    = null;  // /M  - motion: I (inward) or O (outward)
    public ?PdfNumber $di = null;  // /Di - direction in degrees (0, 90, 180, 270, 315)
    public ?PdfNumber $ss = null;  // /SS - scale (Fly only)
    public ?bool $b       = null;  // /B  - opaque (Fly only)

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Trans'));

        if ($this->s !== null) {
            $dict->set('S', $this->s);
        }
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }
        if ($this->dm !== null) {
            $dict->set('Dm', $this->dm);
        }
        if ($this->m !== null) {
            $dict->set('M', $this->m);
        }
        if ($this->di !== null) {
            $dict->set('Di', $this->di);
        }
        if ($this->ss !== null) {
            $dict->set('SS', $this->ss);
        }
        if ($this->b !== null) {
            $dict->set('B', new PdfBoolean($this->b));
        }

        return $dict->toPdf();
    }
}
