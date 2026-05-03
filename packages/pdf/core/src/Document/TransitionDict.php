<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

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
#[RequiresPdfVersion(PdfVersion::V1_1)]
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
