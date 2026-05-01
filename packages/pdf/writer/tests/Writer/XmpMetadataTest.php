<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer\Tests;

use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use ApprLabs\Xmp\XmpPacket;
use ApprLabs\Xmp\XmpWriter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class XmpMetadataTest extends TestCase
{
    use QpdfValidationTrait;

    public function testSetMetadataAddsStreamToPdf(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage();
        $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $packet = XmpPacket::create()
            ->set('dc:title', 'Test Document')
            ->set('dc:creator', 'phpdftk');
        $xmpXml = (new XmpWriter())->serialize($packet);
        $writer->setMetadata($xmpXml);

        $pdf = $writer->generate();

        $this->assertStringContainsString('/Type /Metadata', $pdf);
        $this->assertStringContainsString('/Subtype /XML', $pdf);
        $this->assertStringContainsString('/Metadata', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testMetadataStreamContainsXmp(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage();

        $packet = XmpPacket::create()
            ->set('dc:title', 'My Title')
            ->set('xmp:CreatorTool', 'phpdftk');
        $xmpXml = (new XmpWriter())->serialize($packet);
        $writer->setMetadata($xmpXml);

        $pdf = $writer->generate();

        $this->assertStringContainsString('My Title', $pdf);
        $this->assertStringContainsString('phpdftk', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testMetadataRoundTrip(): void
    {
        $writer = new PdfWriter();
        $writer->addPage();

        $packet = XmpPacket::create()
            ->set('dc:title', 'Round Trip Test');
        $xmpXml = (new XmpWriter())->serialize($packet);
        $writer->setMetadata($xmpXml);

        $pdf = $writer->generate();

        $reader = PdfReader::fromString($pdf);
        $catalog = $reader->getCatalog();
        $this->assertTrue($catalog->has('Metadata'), 'Catalog should reference /Metadata');
        $this->assertQpdfValidBytes($pdf);
    }

    public function testSyncInfoToMetadata(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage();

        $info = new \ApprLabs\Pdf\Core\Document\Info();
        $info->title = new \ApprLabs\Pdf\Core\PdfString('My Title');
        $info->author = new \ApprLabs\Pdf\Core\PdfString('Jane Doe');
        $info->subject = new \ApprLabs\Pdf\Core\PdfString('A test subject');
        $info->creator = new \ApprLabs\Pdf\Core\PdfString('phpdftk test');
        $info->producer = new \ApprLabs\Pdf\Core\PdfString('phpdftk');
        $writer->setInfo($info);

        $writer->syncInfoToMetadata();

        $pdf = $writer->generate();

        $this->assertStringContainsString('My Title', $pdf);
        $this->assertStringContainsString('Jane Doe', $pdf);
        $this->assertStringContainsString('phpdftk', $pdf);
        $this->assertStringContainsString('/Type /Metadata', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testSyncInfoToMetadataNoInfoIsNoOp(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage();
        // No setInfo called
        $writer->syncInfoToMetadata(); // should not crash
        $pdf = $writer->generate();
        // No metadata should be added
        $this->assertStringNotContainsString('/Type /Metadata', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }
}
