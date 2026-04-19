<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\MetadataEditor;
use ApprLabs\Pdf\Toolkit\MetadataInfo;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class MetadataEditorTest extends TestCase
{
    private function generatePdfWithInfo(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->setInfo((function () {
            $info = new Info();
            $info->title = new PdfString('Test Title');
            $info->author = new PdfString('Test Author');
            $info->subject = new PdfString('Test Subject');
            $info->keywords = new PdfString('test, pdf, metadata');
            $info->creator = new PdfString('MetadataEditorTest');
            $info->producer = new PdfString('phpdftk');
            return $info;
        })());

        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Metadata test')
            ->endText();

        return $writer->generate();
    }

    private function generatePdfWithoutInfo(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('No metadata')
            ->endText();

        return $writer->generate();
    }

    public function testReadExistingMetadata(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $editor = MetadataEditor::openString($pdf);

        $this->assertSame('Test Title', $editor->getTitle());
        $this->assertSame('Test Author', $editor->getAuthor());
        $this->assertSame('Test Subject', $editor->getSubject());
        $this->assertSame('test, pdf, metadata', $editor->getKeywords());
        $this->assertSame('MetadataEditorTest', $editor->getCreator());
        $this->assertSame('phpdftk', $editor->getProducer());
    }

    public function testReadNoMetadata(): void
    {
        $pdf = $this->generatePdfWithoutInfo();
        $editor = MetadataEditor::openString($pdf);

        $this->assertNull($editor->getTitle());
        $this->assertNull($editor->getAuthor());
    }

    public function testGetAll(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $editor = MetadataEditor::openString($pdf);
        $all = $editor->getAll();

        $this->assertInstanceOf(MetadataInfo::class, $all);
        $this->assertSame('Test Title', $all->title);
        $this->assertSame('Test Author', $all->author);
        $this->assertSame('Test Subject', $all->subject);
    }

    public function testSetTitleRoundTrip(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $updated = MetadataEditor::openString($pdf)
            ->setTitle('Updated Title')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $updated);

        $editor = MetadataEditor::openString($updated);
        $this->assertSame('Updated Title', $editor->getTitle());
        // Other fields preserved
        $this->assertSame('Test Author', $editor->getAuthor());
    }

    public function testSetMultipleFieldsRoundTrip(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $updated = MetadataEditor::openString($pdf)
            ->setTitle('New Title')
            ->setAuthor('New Author')
            ->setSubject('New Subject')
            ->setKeywords('new, keywords')
            ->toBytes();

        $editor = MetadataEditor::openString($updated);
        $this->assertSame('New Title', $editor->getTitle());
        $this->assertSame('New Author', $editor->getAuthor());
        $this->assertSame('New Subject', $editor->getSubject());
        $this->assertSame('new, keywords', $editor->getKeywords());
        // Unmodified fields preserved
        $this->assertSame('MetadataEditorTest', $editor->getCreator());
    }

    public function testSetMetadataOnPdfWithoutInfo(): void
    {
        $pdf = $this->generatePdfWithoutInfo();
        $updated = MetadataEditor::openString($pdf)
            ->setTitle('Brand New Title')
            ->setAuthor('Brand New Author')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $updated);

        $editor = MetadataEditor::openString($updated);
        $this->assertSame('Brand New Title', $editor->getTitle());
        $this->assertSame('Brand New Author', $editor->getAuthor());
    }

    public function testNoBytesChangedWithoutModifications(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $editor = MetadataEditor::openString($pdf);
        $output = $editor->toBytes();

        $this->assertSame($pdf, $output);
    }

    public function testPageCount(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $editor = MetadataEditor::openString($pdf);
        $this->assertSame(1, $editor->getPageCount());
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $editor = MetadataEditor::openString($pdf);
        $reader = $editor->getReader();
        $this->assertInstanceOf(PdfReader::class, $reader);
    }

    public function testCustomField(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $updated = MetadataEditor::openString($pdf)
            ->setCustom('CustomField', 'CustomValue')
            ->toBytes();

        // Verify the PDF is valid
        $reader = PdfReader::fromString($updated);
        $info = $reader->getInfo();
        $this->assertNotNull($info);
        $val = $info->get('CustomField');
        $this->assertInstanceOf(PdfString::class, $val);
        $this->assertSame('CustomValue', $val->value);
    }

    public function testSaveToFile(): void
    {
        $pdf = $this->generatePdfWithInfo();
        $outputPath = sys_get_temp_dir() . '/phpdftk_metadata_test_' . uniqid() . '.pdf';

        try {
            MetadataEditor::openString($pdf)
                ->setTitle('File Save Test')
                ->save($outputPath);

            $this->assertFileExists($outputPath);
            $this->assertStringStartsWith('%PDF', file_get_contents($outputPath));

            $editor = MetadataEditor::open($outputPath);
            $this->assertSame('File Save Test', $editor->getTitle());
        } finally {
            @unlink($outputPath);
        }
    }
}
