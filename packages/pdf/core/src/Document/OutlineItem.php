<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * PDF Outline Item (bookmark entry).
 *
 * Each item in the bookmarks tree. Items form a doubly-linked list
 * at each level (Prev/Next) and a parent/child hierarchy (Parent/First/Last).
 *
 * /Dest or /A must be set for the bookmark to navigate somewhere.
 */
class OutlineItem extends PdfObject
{
    public PdfString $title;              // /Title - required; displayed label

    public ?PdfReference $parent = null;  // /Parent - required; parent outline item or Outline root
    public ?PdfReference $prev   = null;  // /Prev   - previous sibling
    public ?PdfReference $next   = null;  // /Next   - next sibling
    public ?PdfReference $first  = null;  // /First  - first child
    public ?PdfReference $last   = null;  // /Last   - last child
    public int $count = 0;                // /Count  - negative = subtree closed

    /** /Dest - destination: PdfArray, PdfName (named dest), or string */
    public mixed $dest = null;

    public ?PdfReference $a = null;       // /A - action (alternative to /Dest)

    /** @var PdfArray|null /C - RGB color [r g b], values 0.0–1.0 */
    public ?PdfArray $c = null;

    public int $f = 0;                    // /F - style flags: 1=italic, 2=bold

    public function __construct(string|PdfString $title)
    {
        $this->title = is_string($title) ? new PdfString($title) : $title;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Title', $this->title);

        if ($this->parent !== null) {
            $dict->set('Parent', $this->parent);
        }
        if ($this->prev !== null) {
            $dict->set('Prev', $this->prev);
        }
        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }
        if ($this->first !== null) {
            $dict->set('First', $this->first);
        }
        if ($this->last !== null) {
            $dict->set('Last', $this->last);
        }
        if ($this->count !== 0) {
            $dict->set('Count', new PdfNumber($this->count));
        }
        if ($this->dest !== null) {
            if ($this->dest instanceof \ApprLabs\Pdf\Core\Serializable) {
                $dict->set('Dest', $this->dest);
            } else {
                $dict->set('Dest', new PdfString((string) $this->dest));
            }
        }
        if ($this->a !== null) {
            $dict->set('A', $this->a);
        }
        if ($this->c !== null) {
            $dict->set('C', $this->c);
        }
        if ($this->f !== 0) {
            $dict->set('F', new PdfNumber($this->f));
        }

        return $dict->toPdf();
    }
}
