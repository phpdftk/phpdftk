<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Core\Annotation\AppearanceDict;
use ApprLabs\Pdf\Core\Annotation\TextAnnotation;
use ApprLabs\Pdf\Core\Annotation\WidgetAnnotation;
use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Graphics\XObject\FormXObject;
use ApprLabs\Pdf\Core\Interactive\Form\AcroForm;
use ApprLabs\Pdf\Core\Interactive\Form\AppearanceGenerator;
use ApprLabs\Pdf\Core\Interactive\Form\TextField;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\AnnotationFlattener;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class AnnotationFlattenerTest extends TestCase
{
    private function generatePdfWithAnnotations(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Page with annotations')
            ->endText();

        // Add a text annotation with an appearance stream
        $annot = new TextAnnotation(
            new PdfArray([
                new PdfNumber(100), new PdfNumber(700),
                new PdfNumber(120), new PdfNumber(720),
            ]),
        );
        $annot->contents = new PdfString('A note');

        // Create appearance
        $apXObject = new FormXObject(
            new PdfArray([
                new PdfNumber(0), new PdfNumber(0),
                new PdfNumber(20), new PdfNumber(20),
            ]),
            "1 1 0 rg\n0 0 20 20 re f",
        );
        $apRef = $writer->register($apXObject);

        $annot->ap = AppearanceGenerator::buildAppearanceDict($apRef);
        $annotRef = $writer->register($annot);

        $corePage = $page->corePage();
        $corePage->annots = [$annotRef];

        return $writer->generate();
    }

    public function testFlattenAll(): void
    {
        $pdf = $this->generatePdfWithAnnotations();

        // Verify the original has annotations
        $reader = PdfReader::fromString($pdf);
        $pageDict = $reader->getPage(0);
        $annots = $pageDict->get('Annots');
        $this->assertNotNull($annots);

        // Flatten
        $result = AnnotationFlattener::openString($pdf)
            ->flattenAll()
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $reader2 = PdfReader::fromString($result);
        $this->assertSame(1, $reader2->getPageCount());
    }

    public function testNoOpsReturnsOriginal(): void
    {
        $pdf = $this->generatePdfWithAnnotations();
        $result = AnnotationFlattener::openString($pdf)->toBytes();
        $this->assertSame($pdf, $result);
    }

    public function testPageCount(): void
    {
        $pdf = $this->generatePdfWithAnnotations();
        $flattener = AnnotationFlattener::openString($pdf);
        $this->assertSame(1, $flattener->getPageCount());
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generatePdfWithAnnotations();
        $flattener = AnnotationFlattener::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $flattener->getReader());
    }

    public function testSaveToFile(): void
    {
        $pdf = $this->generatePdfWithAnnotations();
        $path = sys_get_temp_dir() . '/phpdftk_flatten_test_' . uniqid() . '.pdf';

        try {
            AnnotationFlattener::openString($pdf)
                ->flattenAll()
                ->save($path);

            $this->assertFileExists($path);
            $this->assertStringStartsWith('%PDF', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
