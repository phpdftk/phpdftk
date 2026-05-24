<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Encoder\Codabar;
use PHPUnit\Framework\TestCase;

class CodabarTest extends TestCase
{
    public function testEncodesValidStartEndSentinels(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $start) {
            foreach (['A', 'B', 'C', 'D'] as $stop) {
                $bitmap = Codabar::encode("{$start}123{$stop}", new BarcodeOptions());
                self::assertSame(1, $bitmap->rows(), "sentinels {$start}/{$stop} should encode");
            }
        }
    }

    public function testRejectsShortInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Codabar::encode('AB', new BarcodeOptions());
    }

    public function testRejectsBadStartChar(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Codabar::encode('Z123A', new BarcodeOptions());
    }

    public function testRejectsBadStopChar(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Codabar::encode('A123Z', new BarcodeOptions());
    }

    public function testRejectsUnsupportedCharInBody(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Codabar::encode('A1@3B', new BarcodeOptions());
    }

    public function testEncodesPunctuation(): void
    {
        $bitmap = Codabar::encode('A1234-5678.901+B', new BarcodeOptions());
        self::assertGreaterThan(0, $bitmap->columns());
    }
}
