<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Parser;

use Phpdftk\Filters\Ascii85Filter;
use Phpdftk\Filters\AsciiHexFilter;
use Phpdftk\Filters\FlateFilter;
use Phpdftk\Filters\LzwFilter;
use Phpdftk\Filters\PredictorFilter;
use Phpdftk\Filters\RunLengthFilter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Reader\Exception\UnsupportedFilterException;
use Phpdftk\Pdf\Reader\Parser\StreamParser;
use PHPUnit\Framework\TestCase;

class StreamParserTest extends TestCase
{
    private function makeDict(array $entries): PdfDictionary
    {
        $dict = new PdfDictionary();
        foreach ($entries as $k => $v) {
            $dict->set($k, $v);
        }
        return $dict;
    }

    public function testNoFilterReturnsDataAsIs(): void
    {
        $parser = new StreamParser();
        $dict = new PdfDictionary();
        $this->assertSame('raw payload', $parser->decode('raw payload', $dict));
    }

    public function testFlateDecodeRoundTrip(): void
    {
        $payload = 'Hello PDF stream!';
        $encoded = (new FlateFilter())->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('FlateDecode')]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testFlateAbbreviatedNameWorks(): void
    {
        $payload = 'abbrev';
        $encoded = (new FlateFilter())->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('Fl')]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testFilterDeclaredAsStringStillWorks(): void
    {
        $payload = 'plain string filter';
        $encoded = (new FlateFilter())->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => '/FlateDecode']);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testAscii85DecodeWorks(): void
    {
        $payload = 'hello world';
        $encoded = (new Ascii85Filter())->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('ASCII85Decode')]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testAsciiHexDecodeWorks(): void
    {
        $payload = 'hex test';
        $encoded = (new AsciiHexFilter())->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('ASCIIHexDecode')]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testRunLengthDecodeWorks(): void
    {
        $payload = str_repeat('A', 20);
        $encoded = (new RunLengthFilter())->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('RunLengthDecode')]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testDctDecodeReturnsDataUntouched(): void
    {
        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('DCTDecode')]);
        $this->assertSame('FAKE_JPEG_BYTES', $parser->decode('FAKE_JPEG_BYTES', $dict));
    }

    public function testJpxDecodeReturnsDataUntouched(): void
    {
        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('JPXDecode')]);
        $this->assertSame('FAKE_JP2_BYTES', $parser->decode('FAKE_JP2_BYTES', $dict));
    }

    public function testUnsupportedFilterThrows(): void
    {
        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('CryptoGarbageDecode')]);
        $this->expectException(UnsupportedFilterException::class);
        $this->expectExceptionMessage('CryptoGarbageDecode');
        $parser->decode('whatever', $dict);
    }

    public function testFilterArrayChainedDecoding(): void
    {
        // ASCIIHex then Flate (decode order: AHx → FlateDecode)
        $payload = 'chained';
        $flateEncoded = (new FlateFilter())->encode($payload);
        $hexEncoded = (new AsciiHexFilter())->encode($flateEncoded);

        $parser = new StreamParser();
        $dict = $this->makeDict([
            'Filter' => new PdfArray([
                new PdfName('ASCIIHexDecode'),
                new PdfName('FlateDecode'),
            ]),
        ]);
        $this->assertSame($payload, $parser->decode($hexEncoded, $dict));
    }

    public function testFlateWithPredictorRoundTrip(): void
    {
        $columns = 4;
        $bpc = 8;
        $colors = 1;

        // Construct 3 rows of 4 bytes each
        $row1 = "\x10\x20\x30\x40";
        $row2 = "\x11\x22\x33\x44";
        $row3 = "\x12\x24\x36\x48";
        $raw = $row1 . $row2 . $row3;

        $predictor = new PredictorFilter(12, $columns, $colors, $bpc);
        $predictedEncoded = $predictor->encode($raw);
        $flateEncoded = (new FlateFilter())->encode($predictedEncoded);

        $parser = new StreamParser();
        $dict = $this->makeDict([
            'Filter' => new PdfName('FlateDecode'),
            'DecodeParms' => $this->makeDict([
                'Predictor' => new PdfNumber(12),
                'Columns' => new PdfNumber($columns),
                'Colors' => new PdfNumber($colors),
                'BitsPerComponent' => new PdfNumber($bpc),
            ]),
        ]);
        $this->assertSame($raw, $parser->decode($flateEncoded, $dict));
    }

    public function testFlateWithPredictorOneIsNoOp(): void
    {
        $payload = 'no predictor here';
        $encoded = (new FlateFilter())->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict([
            'Filter' => new PdfName('FlateDecode'),
            'DecodeParms' => $this->makeDict(['Predictor' => new PdfNumber(1)]),
        ]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testFilterArrayWithDecodeParmsArray(): void
    {
        $payload = 'array of parms';
        $encoded = (new FlateFilter())->encode($payload);

        // Single-filter chain wrapped in an array, with a DecodeParms array (one entry).
        $parser = new StreamParser();
        $dict = $this->makeDict([
            'Filter' => new PdfArray([new PdfName('FlateDecode')]),
            'DecodeParms' => new PdfArray([
                $this->makeDict(['Predictor' => new PdfNumber(1)]),
            ]),
        ]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testCcittFaxFallsBackOnDecodeFailure(): void
    {
        // Garbage bytes — CCITTFax decode will fail and the parser returns raw data.
        $parser = new StreamParser();
        $dict = $this->makeDict([
            'Filter' => new PdfName('CCITTFaxDecode'),
            'DecodeParms' => $this->makeDict([
                'K' => new PdfNumber(-1),
                'Columns' => new PdfNumber(8),
                'EndOfLine' => new PdfBoolean(true),
                'EncodedByteAlign' => new PdfBoolean(false),
                'EndOfBlock' => new PdfBoolean(false),
                'BlackIs1' => new PdfBoolean(true),
            ]),
        ]);
        $raw = "\xFF\xFF\xFF\xFF";
        $result = $parser->decode($raw, $dict);
        $this->assertIsString($result);
    }

    public function testCcittFaxWithoutDecodeParms(): void
    {
        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('CCITTFaxDecode')]);
        $result = $parser->decode('garbage', $dict);
        $this->assertIsString($result);
    }

    public function testJbig2FallsBackOnDecodeFailure(): void
    {
        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('JBIG2Decode')]);
        $this->assertSame('not-real-jbig2', $parser->decode('not-real-jbig2', $dict));
    }

    public function testLzwAbbreviatedNameWorks(): void
    {
        $payload = 'lzw test';
        $encoded = (new LzwFilter(1))->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfName('LZW')]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testLzwWithEarlyChangeZero(): void
    {
        $payload = 'lzw early=0';
        $encoded = (new LzwFilter(0))->encode($payload);

        $parser = new StreamParser();
        $dict = $this->makeDict([
            'Filter' => new PdfName('LZWDecode'),
            'DecodeParms' => $this->makeDict(['EarlyChange' => new PdfNumber(0)]),
        ]);
        $this->assertSame($payload, $parser->decode($encoded, $dict));
    }

    public function testFilterArrayIgnoresNonNameEntries(): void
    {
        // Filter array containing a non-name entry — resolveFilterNames silently skips it.
        $parser = new StreamParser();
        $dict = $this->makeDict([
            'Filter' => new PdfArray([new PdfNumber(42)]),
        ]);
        $this->assertSame('hello', $parser->decode('hello', $dict));
    }

    public function testInvalidFilterTypeReturnsDataUnchanged(): void
    {
        // Filter set to a number — resolveFilterNames returns [], so loop body is skipped.
        $parser = new StreamParser();
        $dict = $this->makeDict(['Filter' => new PdfNumber(7)]);
        $this->assertSame('untouched', $parser->decode('untouched', $dict));
    }
}
