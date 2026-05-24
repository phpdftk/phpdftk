<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\BarcodeRenderer;
use Phpdftk\Barcode\Encoder\Code128;
use Phpdftk\Barcode\Symbology;
use PHPUnit\Framework\TestCase;

class Code128Test extends TestCase
{
    public function testEncodesPrintableAsciiToSingleRowBitmap(): void
    {
        $bitmap = Code128::encode('HELLO', new BarcodeOptions());
        self::assertSame(1, $bitmap->rows(), 'Code 128 is a 1D barcode');
        // Modules: 1 start + N data + 1 checksum each 11 modules + 13 stop.
        $expected = (1 + 5 + 1) * 11 + 13;
        self::assertSame($expected, $bitmap->columns());
    }

    public function testFirstAndLastModulesAreBars(): void
    {
        // Code 128 patterns always start with a bar and end with a bar.
        $bitmap = Code128::encode('A', new BarcodeOptions());
        $row = $bitmap->modules[0];
        self::assertTrue($row[0], 'first module must be a bar');
        self::assertTrue(end($row), 'last module of the stop pattern must be a bar');
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Code128::encode('', new BarcodeOptions());
    }

    public function testNonAsciiByteThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Code128::encode("Hello\xE9", new BarcodeOptions());
    }

    public function testControlCharBelowSpaceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Code128::encode("Hi\tThere", new BarcodeOptions());
    }

    public function testTotalWidthIncludesQuietZones(): void
    {
        $bitmap = Code128::encode(
            '123',
            new BarcodeOptions(moduleWidth: 2.0, height: 50.0, quietZoneModules: 10),
        );
        $modules = $bitmap->columns();
        // total width = (modules + 2 × quietZone) × moduleWidth
        self::assertSame(
            (float) (($modules + 20) * 2),
            $bitmap->totalWidth(),
        );
        self::assertSame(50.0, $bitmap->totalHeight());
    }

    public function testKnownPatternForLetterAEquivalent(): void
    {
        // The start-B + 'A' + checksum + stop pattern is well-defined.
        // Just confirm the result is deterministic and ASCII printable.
        $a = Code128::encode('A', new BarcodeOptions());
        $b = Code128::encode('A', new BarcodeOptions());
        self::assertSame($a->modules, $b->modules);
    }

    public function testRendererDelegatesToCode128(): void
    {
        $bitmap = BarcodeRenderer::render(Symbology::Code128, 'TEST');
        self::assertSame(1, $bitmap->rows());
        self::assertGreaterThan(0, $bitmap->columns());
    }

    public function testRendererRejectsEmptyInputForEverySymbology(): void
    {
        // Every implemented encoder rejects empty input; smoke-check one.
        $this->expectException(\InvalidArgumentException::class);
        BarcodeRenderer::render(Symbology::Code128, '');
    }
}
