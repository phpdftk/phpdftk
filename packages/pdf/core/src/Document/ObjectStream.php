<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Object stream (/Type /ObjStm) — ISO 32000-2 §7.5.7.
 *
 * A PDF 1.5+ container that holds multiple indirect objects as a single
 * compressed stream. The stream body is laid out as:
 *
 *   <objNum_1> <offset_1> <objNum_2> <offset_2> ... <objNum_N> <offset_N>
 *   <obj_1><obj_2>...<obj_N>
 *
 * `First` is the byte offset at which the first contained object begins
 * (i.e. the length of the header pair sequence). Contained objects are
 * serialized without `obj`/`endobj` wrappers.
 *
 * Only objects without streams and not themselves compressed may be packed.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class ObjectStream extends PdfStream
{
    public const PDF_TYPE = 'ObjStm';

    /** @var array<int, PdfObject> obj number => object */
    private array $contained = [];

    /** Optional /Extends reference to a parent object stream. */
    public ?PdfReference $extends = null;

    public function __construct()
    {
        parent::__construct(new PdfDictionary(), '');
    }

    /**
     * Add an indirect object to the stream. The object must have been
     * assigned an object number already. The object's `toPdf()` output is
     * packed verbatim — `obj`/`endobj` framing is stripped by ObjStm rules.
     */
    public function addObject(PdfObject $object): void
    {
        if ($object->objectNumber === 0) {
            throw new \InvalidArgumentException(
                'ObjectStream requires objects with assigned object numbers'
            );
        }
        $this->contained[$object->objectNumber] = $object;
    }

    /**
     * Number of objects packed in this stream (/N).
     */
    public function count(): int
    {
        return count($this->contained);
    }

    public function toPdf(): string
    {
        $bodies = [];
        $offsets = [];
        $runningOffset = 0;

        foreach ($this->contained as $objNum => $object) {
            $body = $object->toPdf();
            $offsets[] = $objNum . ' ' . $runningOffset;
            $bodies[] = $body;
            $runningOffset += strlen($body) + 1; // +1 for the separator newline
        }

        $header = implode(' ', $offsets);
        // A single space after the header, then each body separated by \n.
        if ($header !== '') {
            $this->data = $header . "\n" . implode("\n", $bodies);
            $first = strlen($header) + 1;
        } else {
            $this->data = '';
            $first = 0;
        }

        $this->dictionary = new PdfDictionary();
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('N', new PdfNumber(count($this->contained)));
        $this->dictionary->set('First', new PdfNumber($first));
        if ($this->extends !== null) {
            $this->dictionary->set('Extends', $this->extends);
        }

        return parent::toPdf();
    }
}
