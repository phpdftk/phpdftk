<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\ObjectStream;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

/**
 * Minimal PdfObject subclass used for ObjectStream packing tests.
 */
final class FakeDictionaryObject extends PdfObject
{
    public function __construct(private readonly PdfDictionary $dict)
    {
    }

    public function toPdf(): string
    {
        return $this->dict->toPdf();
    }
}

class ObjectStreamTest extends TestCase
{
    public function testType(): void
    {
        $os = new ObjectStream();
        $os->objectNumber = 10;
        self::assertStringContainsString('/Type /ObjStm', $os->toPdf());
    }

    public function testEmptyStreamHasZeroCountAndFirst(): void
    {
        $os = new ObjectStream();
        $os->objectNumber = 10;
        $pdf = $os->toPdf();
        self::assertStringContainsString('/N 0', $pdf);
        self::assertStringContainsString('/First 0', $pdf);
        self::assertSame(0, $os->count());
    }

    public function testPacksObjectsAndComputesFirst(): void
    {
        $a = new FakeDictionaryObject(new PdfDictionary(['A' => new PdfNumber(1)]));
        $a->objectNumber = 11;
        $b = new FakeDictionaryObject(new PdfDictionary(['B' => new PdfNumber(2)]));
        $b->objectNumber = 12;

        $os = new ObjectStream();
        $os->objectNumber = 20;
        $os->addObject($a);
        $os->addObject($b);

        self::assertSame(2, $os->count());
        $pdf = $os->toPdf();
        self::assertStringContainsString('/N 2', $pdf);

        // Header "11 0 12 <offB>" -> /First should equal strlen(header)+1
        // Offset of second body is strlen(bodyA) + 1 (separator newline).
        $bodyA = (new PdfDictionary(['A' => new PdfNumber(1)]))->toPdf();
        $offB = strlen($bodyA) + 1;
        $header = '11 0 12 ' . $offB;
        $first = strlen($header) + 1;
        self::assertStringContainsString('/First ' . $first, $pdf);
        self::assertStringContainsString($header, $os->data);
    }

    public function testAddObjectRejectsUnnumberedObject(): void
    {
        $os = new ObjectStream();
        $raw = new FakeDictionaryObject(new PdfDictionary([]));
        $this->expectException(\InvalidArgumentException::class);
        $os->addObject($raw);
    }

    public function testExtendsReference(): void
    {
        $os = new ObjectStream();
        $os->objectNumber = 30;
        $os->extends = new PdfReference(29);
        self::assertStringContainsString('/Extends 29 0 R', $os->toPdf());
    }
}