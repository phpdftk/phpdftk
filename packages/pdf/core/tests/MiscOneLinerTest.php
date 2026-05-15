<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests;

use Phpdftk\Pdf\Core\Graphics\Function\FunctionType4;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * One-liner getter/setter/accessor coverage for methods that aren't
 * naturally exercised by integration tests.
 */
class MiscOneLinerTest extends TestCase
{
    public function testFunctionType4GetTypeAndSerialize(): void
    {
        $domain = new PdfArray([new PdfNumber(0), new PdfNumber(1)]);
        $range = new PdfArray([new PdfNumber(0), new PdfNumber(1)]);
        $f = new FunctionType4($domain, $range, '{ 1 exch sub }');
        $f->objectNumber = 1;
        $this->assertSame(4, $f->getFunctionType());
        $pdf = $f->toIndirectObject();
        $this->assertStringContainsString('/FunctionType 4', $pdf);
        $this->assertStringContainsString('{ 1 exch sub }', $pdf);
    }

    public function testPdfReaderGetObjectReturnsResolvedValue(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage(612, 792);
        $reader = PdfReader::fromString($writer->generate());
        // Catalog is object 1
        $obj = $reader->getObject(1);
        $this->assertNotNull($obj);
    }

    public function testTokenizerGetSourceExposesSource(): void
    {
        // Build a Tokenizer directly and verify getSource() returns the source.
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage(612, 792);
        $bytes = $writer->generate();
        $source = new \Phpdftk\Pdf\Reader\Tokenizer\StringSource($bytes);
        $tokenizer = new \Phpdftk\Pdf\Reader\Tokenizer\Tokenizer($source);
        $this->assertSame($source, $tokenizer->getSource());
    }

    public function testPdfFileWriterSetTsaClientAccepts(): void
    {
        $writer = new \Phpdftk\Pdf\Core\File\PdfFileWriter();
        $tsa = new \Phpdftk\Pdf\Core\Interactive\Signature\TsaClient('https://example.invalid/tsa');
        $writer->setTsaClient($tsa);
        $writer->setCatalog(new \Phpdftk\Pdf\Core\Document\Catalog());
        $this->assertStringStartsWith('%PDF-', $writer->generate());
    }

    public function testPdfFileWriterSetTimestamperInstallsPlaceholders(): void
    {
        $writer = new \Phpdftk\Pdf\Core\File\PdfFileWriter();
        $tsa = new \Phpdftk\Pdf\Core\Interactive\Signature\TsaClient('https://example.invalid/tsa');
        $docTs = new \Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue(
            filter: 'Adobe.PPKLite',
            subFilter: 'ETSI.RFC3161',
        );
        $writer->setTimestamper($docTs, $tsa);
        $this->assertNotNull($docTs->contents);
        $this->assertNotNull($docTs->byteRange);
    }

    public function testAnnotationToPdfDelegatesToBuildDictionary(): void
    {
        $a = new \Phpdftk\Pdf\Core\Annotation\TextAnnotation(new PdfArray([
            new PdfNumber(0), new PdfNumber(0), new PdfNumber(10), new PdfNumber(10),
        ]));
        // Force calling Annotation::toPdf() directly, not the subclass override.
        $reflection = new \ReflectionMethod(\Phpdftk\Pdf\Core\Annotation\Annotation::class, 'toPdf');
        $result = $reflection->invoke($a);
        $this->assertStringContainsString('/Type /Annot', $result);
        $this->assertStringContainsString('/Subtype /Text', $result);
    }
}
