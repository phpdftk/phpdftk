<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * PDF Page Tree node (/Type /Pages).
 * Serves as the root of the page tree and parent of individual Page objects.
 */
class PageTree extends PdfObject
{
    public const PDF_TYPE = 'Pages';

    public ?PdfReference $parent = null;   // /Parent (for non-root nodes)
    /** @var array<int, PdfReference> */
    public array $kids = [];               // /Kids - array of PdfReference to pages/subtrees
    public int $count = 0;                 // /Count - total leaf pages
    public ?PdfArray $mediaBox = null;     // /MediaBox - inherited by pages
    public ?PdfReference $resources = null; // /Resources - inherited by pages
    public int $rotate = 0;                // /Rotate

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->parent !== null) {
            $dict->set('Parent', $this->parent);
        }

        // Build /Kids array
        $kidItems = [];
        foreach ($this->kids as $kid) {
            $kidItems[] = $kid;
        }
        $dict->set('Kids', new PdfArray($kidItems));
        $dict->set('Count', new PdfNumber($this->count));

        if ($this->mediaBox !== null) {
            $dict->set('MediaBox', $this->mediaBox);
        }
        if ($this->resources !== null) {
            $dict->set('Resources', $this->resources);
        }
        if ($this->rotate !== 0) {
            $dict->set('Rotate', new PdfNumber($this->rotate));
        }

        return $dict->toPdf();
    }
}
