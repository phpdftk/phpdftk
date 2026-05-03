<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator;
use Phpdftk\Pdf\Core\Interactive\Form\FontContext;
use Phpdftk\Pdf\Core\Interactive\Form\TextField;
use Phpdftk\Pdf\Core\Interactive\Form\ChoiceField;
use Phpdftk\Pdf\Core\PdfArray;
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
class CustomFontFormAppearancesTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/custom_font_form_appearances.pdf';

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

    public function testGeneratesFormWithCustomFontAppearances(): void
    {
        $fontPath = $this->findFont();
        $ttData = (new TrueTypeParser($fontPath))->parse();

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);

        // Standard font for page labels
        $stdFontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        // Composite font for form field appearances
        $fieldValue = 'Hello World';
        $choiceValue = 'Custom Font';
        $allText = $fieldValue . $choiceValue;
        $codepoints = array_unique(array_map('mb_ord', mb_str_split($allText)));
        $compositeFont = $writer->addCompositeFont($ttData, $codepoints, $page);
        $compositeFontName = $compositeFont->getResourceName();

        // Get the font reference from page resources (wired by addCompositeFont)
        $fontRef = $page->corePage()->resources->font[$compositeFontName];

        // Build FontContext
        $fontCtx = new FontContext(
            fontRef: $fontRef,
            unicodeToGid: $ttData->fullUnicodeToGid,
        );

        // Title using standard font
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($stdFontName, 16)
            ->moveTextPosition(72, 720)
            ->showText('Form with Custom Font Appearances')
            ->setFont($stdFontName, 12)
            ->moveTextPosition(0, -30)
            ->showText('Name (custom font):')
            ->moveTextPosition(0, -40)
            ->showText('Choice (custom font):')
            ->endText();

        // --- Text field with custom font appearance ---
        $nameRect = new PdfArray([
            new PdfNumber(200), new PdfNumber(685),
            new PdfNumber(500), new PdfNumber(705),
        ]);
        $textField = new TextField();
        $textField->t = new PdfString('name');
        $textField->v = new PdfString($fieldValue);
        $textField->da = new PdfString("/$compositeFontName 12 Tf 0 g");
        $writer->register($textField);

        $textAppearance = AppearanceGenerator::textField(
            $nameRect, $compositeFontName, 12, $fieldValue, fontContext: $fontCtx
        );
        $writer->register($textAppearance);

        $textWidget = new WidgetAnnotation($nameRect);
        $textWidget->parent = new PdfReference($textField->objectNumber);
        $textWidget->ap = AppearanceGenerator::buildAppearanceDict(
            new PdfReference($textAppearance->objectNumber)
        );
        $writer->register($textWidget);
        $page->corePage()->annots[] = new PdfReference($textWidget->objectNumber);

        // --- Choice field with custom font appearance ---
        $choiceRect = new PdfArray([
            new PdfNumber(200), new PdfNumber(645),
            new PdfNumber(500), new PdfNumber(665),
        ]);
        $choiceField = new ChoiceField();
        $choiceField->t = new PdfString('style');
        $choiceField->ff = 1 << 17;
        $choiceField->v = new PdfString($choiceValue);
        $writer->register($choiceField);

        $choiceAppearance = AppearanceGenerator::choiceField(
            $choiceRect, $compositeFontName, 12, $choiceValue, fontContext: $fontCtx
        );
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
        $acroForm->needAppearances = false;
        $acroForm->fields = [
            new PdfReference($textField->objectNumber),
            new PdfReference($choiceField->objectNumber),
        ];
        $acroForm->da = new PdfString("/$compositeFontName 12 Tf 0 g");
        $writer->register($acroForm);
        $writer->getCatalog()->acroForm = new PdfReference($acroForm->objectNumber);

        // Save
        $dir = dirname(self::OUTPUT_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $writer->save(self::OUTPUT_FILE);

        // --- Assertions ---
        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $contents = file_get_contents(self::OUTPUT_FILE);
        self::assertStringStartsWith('%PDF-', $contents);
        self::assertStringContainsString('/NeedAppearances false', $contents);
        self::assertStringContainsString('/Subtype /Form', $contents);
        // Hex-encoded text operator should be present
        self::assertStringContainsString('<', $contents);
        self::assertStringContainsString('> Tj', $contents);
        // Font reference in appearance resources
        self::assertStringContainsString("/$compositeFontName", $contents);
        // CIDFont infrastructure
        self::assertStringContainsString('/Identity-H', $contents);
        self::assertStringContainsString('/CIDFontType2', $contents);

        // Round-trip: reader should parse it
        $reader = PdfReader::fromFile(self::OUTPUT_FILE);
        self::assertSame(1, $reader->getPageCount());
        $catalog = $reader->getCatalog();
        self::assertTrue($catalog->has('AcroForm'));
    }
}
