<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * XMP metadata stream (/Type /Metadata /Subtype /XML) —
 * ISO 32000-2 §14.3.2, Table 339.
 *
 * Thin typed wrapper around a `PdfStream` that sets the two required
 * header entries. Use `packages/xmp` to generate the packet bytes and
 * pass them here; assign the resulting indirect reference to
 * `Catalog::$metadata`, `Page::$metadata`, etc.
 */
#[RequiresPdfVersion(PdfVersion::V1_4)]
class MetadataStream extends PdfStream
{
    public const PDF_TYPE = 'Metadata';
    public const PDF_SUBTYPE = 'XML';

    public function __construct(string $xmpPacket = '')
    {
        parent::__construct(new PdfDictionary(), $xmpPacket);
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('Subtype', new PdfName(self::PDF_SUBTYPE));
        return parent::toPdf();
    }
}
