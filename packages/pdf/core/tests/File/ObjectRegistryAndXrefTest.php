<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\File;

use Phpdftk\Pdf\Core\File\CrossReferenceTable;
use Phpdftk\Pdf\Core\File\ObjectRegistry;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use PHPUnit\Framework\TestCase;

class ObjectRegistryAndXrefTest extends TestCase
{
    // -----------------------------------------------------------------------
    // CrossReferenceTable
    // -----------------------------------------------------------------------

    public function testCrossReferenceTableBuildStartsWithXref(): void
    {
        $xref = new CrossReferenceTable();
        $result = $xref->build(1);
        self::assertStringStartsWith("xref\n", $result);
    }

    public function testCrossReferenceTableSizeInHeader(): void
    {
        $xref = new CrossReferenceTable();
        $result = $xref->build(5);
        self::assertStringContainsString("0 5\n", $result);
    }

    public function testCrossReferenceTableFreeListHead(): void
    {
        $xref = new CrossReferenceTable();
        $result = $xref->build(1);
        self::assertStringContainsString('0000000000 65535 f', $result);
    }

    public function testCrossReferenceTableWithEntry(): void
    {
        $xref = new CrossReferenceTable();
        $xref->add(1, 15);
        $result = $xref->build(2);
        self::assertStringContainsString('0000000015 00000 n', $result);
    }

    public function testCrossReferenceTableWithMultipleEntries(): void
    {
        $xref = new CrossReferenceTable();
        $xref->add(1, 15);
        $xref->add(2, 200);
        $xref->add(3, 512);
        $result = $xref->build(4);
        self::assertStringContainsString('0000000015 00000 n', $result);
        self::assertStringContainsString('0000000200 00000 n', $result);
        self::assertStringContainsString('0000000512 00000 n', $result);
    }

    public function testCrossReferenceTableEntryFormat(): void
    {
        $xref = new CrossReferenceTable();
        $xref->add(1, 9999);
        $result = $xref->build(2);
        self::assertStringContainsString('0000009999', $result);
    }

    public function testCrossReferenceTableMissingEntryDefaultsToZero(): void
    {
        $xref = new CrossReferenceTable();
        $result = $xref->build(2);
        self::assertStringContainsString('0000000000 00000 n', $result);
    }

    public function testCrossReferenceTableEntriesAreExactly20Bytes(): void
    {
        // ISO 32000-2 §7.5.4 requires each xref entry to be exactly 20
        // bytes so readers can seek directly by index. The two-character
        // EOL must therefore be `CR LF` (no trailing space) or `SP CR` /
        // `SP LF` (single-char EOL).
        $xref = new CrossReferenceTable();
        $xref->add(1, 15);
        $xref->add(2, 200);
        $result = $xref->build(3);
        // Drop the "xref\n" + "0 3\n" header lines.
        $body = substr($result, (int) strpos($result, "\n", (int) strpos($result, "\n") + 1) + 1);
        $entries = explode("\r\n", $body);
        // Last element is empty (trailing terminator); drop it.
        array_pop($entries);
        self::assertCount(3, $entries);
        foreach ($entries as $entry) {
            self::assertSame(
                18,
                strlen($entry),
                "Each xref entry's visible body must be 18 bytes (+2-byte CRLF = 20-byte total): got [$entry]",
            );
        }
    }

    // -----------------------------------------------------------------------
    // ObjectRegistry
    // -----------------------------------------------------------------------

    public function testObjectRegistryInitialSizeIsOne(): void
    {
        $reg = new ObjectRegistry();
        self::assertSame(1, $reg->getSize());
    }

    public function testObjectRegistryRegisterReturnsObjectNumber(): void
    {
        $reg = new ObjectRegistry();
        $obj = new Type1Font(StandardFont::Helvetica);
        $num = $reg->register($obj);
        self::assertSame(1, $num);
        self::assertSame(1, $obj->objectNumber);
    }

    public function testObjectRegistrySequentialNumbering(): void
    {
        $reg = new ObjectRegistry();
        $obj1 = new Type1Font(StandardFont::Helvetica);
        $obj2 = new Type1Font(StandardFont::Courier);
        $num1 = $reg->register($obj1);
        $num2 = $reg->register($obj2);
        self::assertSame(1, $num1);
        self::assertSame(2, $num2);
    }

    public function testObjectRegistryGetAll(): void
    {
        $reg = new ObjectRegistry();
        $obj = new Type1Font(StandardFont::Helvetica);
        $reg->register($obj);
        $all = $reg->getAll();
        self::assertCount(1, $all);
        self::assertSame($obj, $all[1]);
    }

    public function testObjectRegistrySizeAfterRegistration(): void
    {
        $reg = new ObjectRegistry();
        $obj1 = new Type1Font(StandardFont::Helvetica);
        $obj2 = new Type1Font(StandardFont::Courier);
        $obj3 = new Type1Font(StandardFont::TimesRoman);
        $reg->register($obj1);
        $reg->register($obj2);
        $reg->register($obj3);
        self::assertSame(4, $reg->getSize()); // 0 (free head) + 3 objects
    }

    public function testObjectRegistryGenerationNumberIsZero(): void
    {
        $reg = new ObjectRegistry();
        $obj = new Type1Font(StandardFont::Helvetica);
        $reg->register($obj);
        self::assertSame(0, $obj->generationNumber);
    }
}
