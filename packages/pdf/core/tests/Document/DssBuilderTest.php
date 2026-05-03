<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\DssBuilder;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class DssBuilderTest extends TestCase
{
    private function createTestPdf(): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($font, 12)->moveTextPosition(72, 720)->showText('DSS test')->endText();
        return $writer->generate();
    }

    private function createWriter(): IncrementalWriter
    {
        $pdf = $this->createTestPdf();
        $reader = PdfReader::fromString($pdf);
        return IncrementalWriter::fromReader($reader, $pdf);
    }

    public function testBuildProducesDssWithCerts(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $certData = random_bytes(256); // simulate DER cert
        $ref = $builder->addCertificate($certData);

        self::assertInstanceOf(PdfReference::class, $ref);

        $dss = $builder->build();
        self::assertNotNull($dss->certs);
        self::assertInstanceOf(PdfArray::class, $dss->certs);
        self::assertCount(1, $dss->certs->items);
    }

    public function testBuildProducesDssWithOcsps(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $ocspData = random_bytes(128);
        $ref = $builder->addOcspResponse($ocspData);

        self::assertInstanceOf(PdfReference::class, $ref);

        $dss = $builder->build();
        self::assertNotNull($dss->ocsps);
        self::assertCount(1, $dss->ocsps->items);
    }

    public function testBuildProducesDssWithCrls(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $crlData = random_bytes(512);
        $ref = $builder->addCrl($crlData);

        self::assertInstanceOf(PdfReference::class, $ref);

        $dss = $builder->build();
        self::assertNotNull($dss->crls);
        self::assertCount(1, $dss->crls->items);
    }

    public function testBuildProducesDssWithVri(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $certRef = $builder->addCertificate(random_bytes(256));
        $ocspRef = $builder->addOcspResponse(random_bytes(128));

        $sigHash = DssBuilder::computeVriKey('test signature bytes');
        $builder->addVriEntry($sigHash, [$certRef], [$ocspRef], []);

        $dss = $builder->build();
        self::assertNotNull($dss->vri);
        self::assertInstanceOf(PdfDictionary::class, $dss->vri);

        $vriEntry = $dss->vri->get($sigHash);
        self::assertNotNull($vriEntry, 'VRI entry should exist for signature hash');
        self::assertInstanceOf(PdfDictionary::class, $vriEntry);
    }

    public function testDeduplicatesIdenticalCerts(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $certData = random_bytes(256);
        $ref1 = $builder->addCertificate($certData);
        $ref2 = $builder->addCertificate($certData); // same data

        self::assertSame($ref1->objectNumber, $ref2->objectNumber, 'Same cert should return same reference');

        $dss = $builder->build();
        self::assertCount(1, $dss->certs->items);
    }

    public function testDeduplicatesIdenticalOcsps(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $ocspData = random_bytes(128);
        $ref1 = $builder->addOcspResponse($ocspData);
        $ref2 = $builder->addOcspResponse($ocspData);

        self::assertSame($ref1->objectNumber, $ref2->objectNumber);
    }

    public function testDeduplicatesIdenticalCrls(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $crlData = random_bytes(512);
        $ref1 = $builder->addCrl($crlData);
        $ref2 = $builder->addCrl($crlData);

        self::assertSame($ref1->objectNumber, $ref2->objectNumber);
    }

    public function testDifferentDataGetsDifferentReferences(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $ref1 = $builder->addCertificate(random_bytes(256));
        $ref2 = $builder->addCertificate(random_bytes(256));

        self::assertNotSame($ref1->objectNumber, $ref2->objectNumber);

        $dss = $builder->build();
        self::assertCount(2, $dss->certs->items);
    }

    public function testVriKeyIsUppercaseHexSha256(): void
    {
        $data = 'some signature bytes';
        $key = DssBuilder::computeVriKey($data);

        // Should be 64-char uppercase hex string
        self::assertSame(64, strlen($key));
        self::assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $key);
        self::assertSame(strtoupper(hash('sha256', $data)), $key);
    }

    public function testEmptyBuilderProducesMinimalDss(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $dss = $builder->build();
        self::assertNull($dss->certs);
        self::assertNull($dss->ocsps);
        self::assertNull($dss->crls);
        self::assertNull($dss->vri);
    }

    public function testDssSerializesToPdf(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $builder->addCertificate(random_bytes(256));
        $builder->addOcspResponse(random_bytes(128));

        $dss = $builder->build();
        $pdf = $dss->toPdf();

        self::assertStringContainsString('/Certs', $pdf);
        self::assertStringContainsString('/OCSPs', $pdf);
    }

    public function testMultipleVriEntries(): void
    {
        $writer = $this->createWriter();
        $builder = new DssBuilder($writer);

        $certRef1 = $builder->addCertificate(random_bytes(256));
        $certRef2 = $builder->addCertificate(random_bytes(256));

        $key1 = DssBuilder::computeVriKey('signature 1');
        $key2 = DssBuilder::computeVriKey('signature 2');

        $builder->addVriEntry($key1, [$certRef1], [], []);
        $builder->addVriEntry($key2, [$certRef2], [], []);

        $dss = $builder->build();
        self::assertNotNull($dss->vri);
        self::assertNotNull($dss->vri->get($key1));
        self::assertNotNull($dss->vri->get($key2));
    }
}
