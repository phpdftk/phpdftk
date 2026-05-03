<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Integration;

use Phpdftk\Pdf\Conformance\ConformanceException;
use Phpdftk\Pdf\Conformance\Profile\PdfMailProfile;
use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Annotation\ScreenAnnotation;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\TextField;
use Phpdftk\Pdf\Core\Multimedia\MediaRendition;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfMailIntegrationTest extends TestCase
{
    private const OUTPUT_DIR = __DIR__ . '/../../tests/output';

    protected function setUp(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0o755, true);
        }
    }

    private function findFont(): string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No TTF font found on this system');
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function testCompliantPdfMail1(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfMailProfile::Mail1);

        $info = new Info();
        $info->title = new PdfString('PDF/mail-1 Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/mail-1 compliant document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfmail1_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    public function testMail1AutoInjectsXmp(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfMailProfile::Mail1, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();
        self::assertStringContainsString('pdfmailid:part', $pdf);
    }

    public function testMail1PinsTo2_0(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfMailProfile::Mail1, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF-2.0', $pdf);
    }

    // -----------------------------------------------------------------------
    // Sad path
    // -----------------------------------------------------------------------

    public function testMail1WithJavaScriptFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfMailProfile::Mail1, strict: false);

        $info = new Info();
        $info->title = new PdfString('PDF/mail JS');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $jsAction = new JavaScriptAction(new PdfString('app.alert("test")'));
        $writer->register($jsAction);

        $writer->generate();
        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);
    }

    public function testMail1WithFormsFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfMailProfile::Mail1, strict: false);

        $info = new Info();
        $info->title = new PdfString('PDF/mail Forms');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        // Add AcroForm to the catalog
        $acroForm = new AcroForm();
        $acroForm->objectNumber = 100;
        $acroForm->generationNumber = 0;
        $writer->getCatalog()->acroForm = new PdfReference($acroForm->objectNumber);

        $results = $writer->checkConformance();
        $errors = $results[0]->getErrors();
        $formErrors = array_filter(
            $errors,
            fn($v) => str_contains($v->message, 'AcroForm'),
        );
        self::assertNotEmpty($formErrors, 'Expected a form constraint violation');
    }

    public function testMail1WithMultimediaFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfMailProfile::Mail1, strict: false);

        $info = new Info();
        $info->title = new PdfString('PDF/mail Multimedia');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        // Add a ScreenAnnotation (multimedia)
        $rect = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
        $screen = new ScreenAnnotation($rect);
        $writer->register($screen);

        $results = $writer->checkConformance();
        $errors = $results[0]->getErrors();
        $mmErrors = array_filter(
            $errors,
            fn($v) => str_contains($v->message, 'Multimedia'),
        );
        self::assertNotEmpty($mmErrors, 'Expected a multimedia constraint violation');
    }
}
