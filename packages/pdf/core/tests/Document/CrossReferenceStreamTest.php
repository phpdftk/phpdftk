<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\Pdf\Core\Document\CrossReferenceStream;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class CrossReferenceStreamTest extends TestCase
{
    public function testType(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 5;
        self::assertStringContainsString('/Type /XRef', $xref->toPdf());
    }

    public function testSize(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 42;
        self::assertStringContainsString('/Size 42', $xref->toPdf());
    }

    public function testWidthsAutoDetected(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 3;
        $xref->addFreeEntry(0, 65535);
        $xref->addInUseEntry(1000, 0);
        $pdf = $xref->toPdf();
        // Offset 1000 fits in 2 bytes, generation 65535 fits in 2 bytes
        self::assertStringContainsString('/W [ 1 2 2 ]', $pdf);
    }

    public function testRootInfoAndId(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 3;
        $xref->root = new PdfReference(1);
        $xref->info = new PdfReference(2);
        $xref->id = new PdfArray([
            new \ApprLabs\Pdf\Core\PdfString(str_repeat('a', 16), hex: false),
            new \ApprLabs\Pdf\Core\PdfString(str_repeat('a', 16), hex: false),
        ]);
        $pdf = $xref->toPdf();
        self::assertStringContainsString('/Root 1 0 R', $pdf);
        self::assertStringContainsString('/Info 2 0 R', $pdf);
        self::assertStringContainsString('/ID', $pdf);
    }

    public function testPrev(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 1;
        $xref->prev = 1234;
        self::assertStringContainsString('/Prev 1234', $xref->toPdf());
    }

    public function testIndexField(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 4;
        $xref->index = new PdfArray([new PdfNumber(0), new PdfNumber(4)]);
        self::assertStringContainsString('/Index [ 0 4 ]', $xref->toPdf());
    }

    public function testPackedEntriesUseOptimalWidths(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 3;
        $xref->addFreeEntry(0, 65535);
        $xref->addInUseEntry(15, 0);
        $xref->addInUseEntry(256, 0);

        // Force packing
        $xref->packAllEntries();

        // Max offset is 256 (fits in 2 bytes), max gen is 65535 (fits in 2 bytes)
        // Entry width: 1 + 2 + 2 = 5 bytes × 3 entries = 15 bytes
        self::assertSame(15, strlen($xref->data));

        // First byte of first entry is 0 (free)
        self::assertSame(0, ord($xref->data[0]));
        // First byte of second entry is 1 (in use), at offset 5
        self::assertSame(1, ord($xref->data[5]));
    }

    public function testIsIndirectObject(): void
    {
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 10;
        $xref->size = 0;
        self::assertStringContainsString('10 0 obj', $xref->toIndirectObject());
        self::assertStringContainsString('stream', $xref->toIndirectObject());
    }
}
