<?php

declare(strict_types=1);

namespace ApprLabs\Filters\Tests;

use PHPUnit\Framework\TestCase;
use ApprLabs\Filters\PredictorFilter;

class PredictorFilterTest extends TestCase
{
    // -----------------------------------------------------------------------
    // No prediction (predictor = 1)
    // -----------------------------------------------------------------------

    public function testNoPredictionPassesThrough(): void
    {
        $f = new PredictorFilter(predictor: 1);
        $data = 'Hello World';
        $this->assertSame($data, $f->decode($data));
        $this->assertSame($data, $f->encode($data));
    }

    // -----------------------------------------------------------------------
    // TIFF Predictor 2
    // -----------------------------------------------------------------------

    public function testTiffPredictorRoundTrip(): void
    {
        // 1 color, 8 bpc, 4 columns = 4 bytes per row
        $f = new PredictorFilter(predictor: 2, columns: 4, colors: 1, bitsPerComponent: 8);
        $data = "\x10\x20\x30\x40\x50\x60\x70\x80";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    public function testTiffPredictorRgbRoundTrip(): void
    {
        // 3 colors (RGB), 8 bpc, 2 columns = 6 bytes per row
        $f = new PredictorFilter(predictor: 2, columns: 2, colors: 3, bitsPerComponent: 8);
        $data = "\xFF\x00\x80\xCC\x33\x66";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    // -----------------------------------------------------------------------
    // PNG None (predictor = 10)
    // -----------------------------------------------------------------------

    public function testPngNoneRoundTrip(): void
    {
        $f = new PredictorFilter(predictor: 10, columns: 3, colors: 1, bitsPerComponent: 8);
        $data = "\xAA\xBB\xCC";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    // -----------------------------------------------------------------------
    // PNG Sub (predictor = 11)
    // -----------------------------------------------------------------------

    public function testPngSubRoundTrip(): void
    {
        $f = new PredictorFilter(predictor: 11, columns: 4, colors: 1, bitsPerComponent: 8);
        $data = "\x10\x20\x30\x40";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    public function testPngSubMultiRowRoundTrip(): void
    {
        $f = new PredictorFilter(predictor: 11, columns: 3, colors: 1, bitsPerComponent: 8);
        $data = "\x01\x02\x03\x04\x05\x06";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    // -----------------------------------------------------------------------
    // PNG Up (predictor = 12)
    // -----------------------------------------------------------------------

    public function testPngUpRoundTrip(): void
    {
        $f = new PredictorFilter(predictor: 12, columns: 3, colors: 1, bitsPerComponent: 8);
        $data = "\x10\x20\x30\x40\x50\x60";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    // -----------------------------------------------------------------------
    // PNG Average (predictor = 13)
    // -----------------------------------------------------------------------

    public function testPngAverageRoundTrip(): void
    {
        $f = new PredictorFilter(predictor: 13, columns: 4, colors: 1, bitsPerComponent: 8);
        $data = "\x10\x20\x30\x40\x50\x60\x70\x80";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    // -----------------------------------------------------------------------
    // PNG Paeth (predictor = 14)
    // -----------------------------------------------------------------------

    public function testPngPaethRoundTrip(): void
    {
        $f = new PredictorFilter(predictor: 14, columns: 4, colors: 1, bitsPerComponent: 8);
        $data = "\x10\x20\x30\x40\x50\x60\x70\x80";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    // -----------------------------------------------------------------------
    // PNG Optimum (predictor = 15) — uses Up as default encode strategy
    // -----------------------------------------------------------------------

    public function testPngOptimumRoundTrip(): void
    {
        $f = new PredictorFilter(predictor: 15, columns: 4, colors: 1, bitsPerComponent: 8);
        $data = "\x10\x20\x30\x40\x50\x60\x70\x80";
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }

    // -----------------------------------------------------------------------
    // Decoding with per-row tag bytes (mixed predictors)
    // -----------------------------------------------------------------------

    public function testPngDecodeWithMixedTags(): void
    {
        // 3 columns, 1 color, 8 bpc → 3 bytes per row + 1 tag byte = 4 bytes per stride
        $f = new PredictorFilter(predictor: 15, columns: 3, colors: 1, bitsPerComponent: 8);

        // Row 1: tag=0 (None), data=\x01\x02\x03
        // Row 2: tag=2 (Up), data=\x01\x01\x01 → decoded = \x02\x03\x04
        $encoded = "\x00\x01\x02\x03\x02\x01\x01\x01";
        $decoded = $f->decode($encoded);

        $this->assertSame("\x01\x02\x03\x02\x03\x04", $decoded);
    }

    // -----------------------------------------------------------------------
    // Large data round-trip
    // -----------------------------------------------------------------------

    public function testLargeDataRoundTrip(): void
    {
        $data = '';
        for ($i = 0; $i < 100; $i++) {
            $data .= chr($i % 256) . chr(($i * 7) % 256) . chr(($i * 13) % 256);
        }
        // 3 columns, 1 color, 8 bpc
        $f = new PredictorFilter(predictor: 12, columns: 3, colors: 1, bitsPerComponent: 8);
        $encoded = $f->encode($data);
        $this->assertSame($data, $f->decode($encoded));
    }
}
