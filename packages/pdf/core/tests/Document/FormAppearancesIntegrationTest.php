<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator;
use Phpdftk\Pdf\Core\Interactive\Form\ButtonField;
use Phpdftk\Pdf\Core\Interactive\Form\ChoiceField;
use Phpdftk\Pdf\Core\Interactive\Form\TextField;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class FormAppearancesIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/form_appearances.pdf';

    public function testGeneratesFormWithAppearances(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        // Title
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 16)
            ->moveTextPosition(72, 720)
            ->showText('Form with Generated Appearances')
            ->setFont($fontName, 12)
            ->moveTextPosition(0, -30)
            ->showText('Name:')
            ->moveTextPosition(0, -40)
            ->showText('Accept Terms:')
            ->moveTextPosition(0, -40)
            ->showText('Country:')
            ->endText();

        // --- Text field with appearance ---
        $nameRect = new PdfArray([
            new PdfNumber(150), new PdfNumber(685),
            new PdfNumber(400), new PdfNumber(705),
        ]);
        $textField = new TextField();
        $textField->t = new PdfString('name');
        $textField->v = new PdfString('John Doe');
        $textField->da = new PdfString("/$fontName 12 Tf 0 g");
        $writer->register($textField);

        $textAppearance = AppearanceGenerator::textField($nameRect, $fontName, 12, 'John Doe');
        $textAppearance->resources = new Resources();
        $writer->register($textAppearance);

        $textWidget = new WidgetAnnotation($nameRect);
        $textWidget->parent = new PdfReference($textField->objectNumber);
        $textWidget->ap = AppearanceGenerator::buildAppearanceDict(
            new PdfReference($textAppearance->objectNumber)
        );
        $writer->register($textWidget);
        $page->corePage()->annots[] = new PdfReference($textWidget->objectNumber);

        // --- Checkbox with appearance ---
        $checkRect = new PdfArray([
            new PdfNumber(150), new PdfNumber(645),
            new PdfNumber(168), new PdfNumber(663),
        ]);
        $checkField = new ButtonField();
        $checkField->t = new PdfString('accept');
        $checkField->v = new PdfName('Yes');
        $writer->register($checkField);

        $checkStates = AppearanceGenerator::checkbox($checkRect);
        $writer->register($checkStates['on']);
        $writer->register($checkStates['off']);

        $checkWidget = new WidgetAnnotation($checkRect);
        $checkWidget->parent = new PdfReference($checkField->objectNumber);
        $checkWidget->as = new PdfName('Yes');
        $checkWidget->ap = AppearanceGenerator::buildStateAppearanceDict(
            new PdfReference($checkStates['on']->objectNumber),
            new PdfReference($checkStates['off']->objectNumber),
        );
        $writer->register($checkWidget);
        $page->corePage()->annots[] = new PdfReference($checkWidget->objectNumber);

        // --- Choice field with appearance ---
        $choiceRect = new PdfArray([
            new PdfNumber(150), new PdfNumber(605),
            new PdfNumber(400), new PdfNumber(625),
        ]);
        $choiceField = new ChoiceField();
        $choiceField->t = new PdfString('country');
        $choiceField->ff = 1 << 17; // Combo box
        $choiceField->opt = new PdfArray([
            new PdfString('United States'),
            new PdfString('Canada'),
            new PdfString('United Kingdom'),
        ]);
        $choiceField->v = new PdfString('United States');
        $writer->register($choiceField);

        $choiceAppearance = AppearanceGenerator::choiceField($choiceRect, $fontName, 12, 'United States');
        $choiceAppearance->resources = new Resources();
        $writer->register($choiceAppearance);

        $choiceWidget = new WidgetAnnotation($choiceRect);
        $choiceWidget->parent = new PdfReference($choiceField->objectNumber);
        $choiceWidget->ap = AppearanceGenerator::buildAppearanceDict(
            new PdfReference($choiceAppearance->objectNumber)
        );
        $writer->register($choiceWidget);
        $page->corePage()->annots[] = new PdfReference($choiceWidget->objectNumber);

        // --- AcroForm ---
        $acroForm = new AcroForm();
        $acroForm->needAppearances = false; // We provide appearances
        $acroForm->fields = [
            new PdfReference($textField->objectNumber),
            new PdfReference($checkField->objectNumber),
            new PdfReference($choiceField->objectNumber),
        ];
        $acroForm->da = new PdfString("/$fontName 12 Tf 0 g");
        $writer->register($acroForm);
        $writer->getCatalog()->acroForm = new PdfReference($acroForm->objectNumber);

        // Save
        $dir = dirname(self::OUTPUT_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $writer->save(self::OUTPUT_FILE);

        // Assertions
        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $contents = file_get_contents(self::OUTPUT_FILE);
        self::assertStringStartsWith('%PDF-', $contents);
        self::assertStringContainsString('/NeedAppearances false', $contents);
        self::assertStringContainsString('/Subtype /Form', $contents);

        // Round-trip: reader should parse it
        $reader = PdfReader::fromFile(self::OUTPUT_FILE);
        self::assertSame(1, $reader->getPageCount());
        $catalog = $reader->getCatalog();
        self::assertTrue($catalog->has('AcroForm'));
    }
}
