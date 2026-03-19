<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\XObject;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfStream;

/**
 * Form XObject (/Subtype /Form).
 * A self-contained content stream that can be placed on a page.
 */
class FormXObject extends PdfStream
{
    public const PDF_TYPE    = 'XObject';
    public const PDF_SUBTYPE = 'Form';

    public PdfArray $bBox;                    // /BBox - required
    public ?PdfArray $matrix = null;          // /Matrix
    public ?PdfDictionary $resources = null;  // /Resources
    public ?PdfName $formType = null;         // /FormType

    public function __construct(PdfArray $bBox, string $data = '')
    {
        parent::__construct(new PdfDictionary(), $data);
        $this->bBox = $bBox;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('Subtype', new PdfName(self::PDF_SUBTYPE));
        $this->dictionary->set('BBox', $this->bBox);

        if ($this->matrix !== null) {
            $this->dictionary->set('Matrix', $this->matrix);
        }
        if ($this->resources !== null) {
            $this->dictionary->set('Resources', $this->resources);
        }
        if ($this->formType !== null) {
            $this->dictionary->set('FormType', $this->formType);
        }

        return parent::toPdf();
    }
}
