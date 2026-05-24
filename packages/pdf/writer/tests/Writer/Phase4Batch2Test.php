<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Geometry\Point;
use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Core\Multimedia\Sound;
use Phpdftk\Pdf\Core\Multimedia\Movie;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;
use Phpdftk\Pdf\Writer\Form\CheckboxOptions;
use Phpdftk\Pdf\Writer\Form\ChoiceFieldOptions;
use Phpdftk\Pdf\Writer\Form\TextFieldOptions;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Pdf\Writer\SpotColor;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class Phase4Batch2Test extends TestCase
{
    use QpdfValidationTrait;

    // -----------------------------------------------------------------------
    // 4.12 — Form XObject templates
    // -----------------------------------------------------------------------

    public function testCreateTemplateRegistersFormXObject(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $template = $doc->createTemplate(
            new Rectangle(0, 0, 100, 50),
            function ($cs): void {
                $cs->moveTo(0, 0)->lineTo(100, 50)->stroke();
            },
        );
        self::assertGreaterThan(0, $template->objectNumber);

        $page = $doc->addPage();
        $page->drawTemplate($template, 72, 720);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Form', $bytes);
        self::assertMatchesRegularExpression('/\/BBox \[ 0 0 100 50 \]/', $bytes);
        // Page references the template via /Do
        self::assertMatchesRegularExpression('/\/Tpl\d+ Do/', $bytes);
    }

    public function testDrawTemplateScalesByGivenWidth(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $template = $doc->createTemplate(new Rectangle(0, 0, 100, 50), fn($cs) => null);

        $page = $doc->addPage();
        $page->drawTemplate($template, 0, 0, 200);  // 200 wide → scale 2x in x
        $bytes = $doc->writer()->generate();
        // CTM scale factor 2 on the X axis appears in the cm matrix.
        self::assertMatchesRegularExpression('/2 0 0 [\d.]+ /', $bytes);
    }

    public function testDrawTemplateReusesResourceForRepeatedCalls(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $template = $doc->createTemplate(new Rectangle(0, 0, 50, 50), fn($cs) => null);

        $page = $doc->addPage();
        $page->drawTemplate($template, 0, 0);
        $page->drawTemplate($template, 100, 100);

        $resources = $page->corePage()->resources;
        self::assertNotNull($resources);
        // One XObject entry on the page, used twice in the content stream.
        self::assertCount(1, $resources->xObject);
    }

    // -----------------------------------------------------------------------
    // 4.11 — Spot colors
    // -----------------------------------------------------------------------

    public function testRegisterSpotColorReturnsSpotHandle(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $spot = $doc->registerSpotColor('Pantone 185 C', [0, 0.85, 0.6, 0]);
        self::assertInstanceOf(SpotColor::class, $spot);
        self::assertSame('Pantone 185 C', $spot->name);
    }

    public function testSpotColorRegistersTintFunctionAndSeparationCanBeUsed(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $spot = $doc->registerSpotColor('Pantone 185 C', [0, 0.85, 0.6, 0]);
        $name = $page->useSpotColor($spot);

        $page->contentStream()
            ->setFillColorSpace($name)
            ->setFillColor(0.75)
            ->rectangle(72, 600, 200, 80)
            ->fill();

        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/FunctionType 2', $bytes);
        self::assertStringContainsString('/Separation', $bytes);
        // Name spaces are encoded as #20 inside PDF Name objects.
        self::assertStringContainsString('Pantone#20185#20C', $bytes);
        // The fill-colour-space operator references the page resource.
        self::assertStringContainsString('/CS_Pantone_185_C cs', $bytes);
    }

    public function testSpotColorReusesResourceAcrossUseCalls(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $spot = $doc->registerSpotColor('Spot', [0, 0, 0, 1]);
        $name1 = $page->useSpotColor($spot);
        $name2 = $page->useSpotColor($spot);
        self::assertSame($name1, $name2);
    }

    // -----------------------------------------------------------------------
    // 4.5 — Gradients
    // -----------------------------------------------------------------------

    public function testLinearGradientRegistersShadingPattern(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $gradient = $doc->addLinearGradient(
            new Point(0, 0),
            new Point(200, 0),
            [1, 0, 0],
            [0, 0, 1],
        );
        $name = $page->useGradient($gradient);

        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/ShadingType 2', $bytes);
        self::assertStringContainsString('/Pattern', $bytes);
        // Coordinates land in the shading dict.
        self::assertMatchesRegularExpression('/\/Coords \[ 0 0 200 0 \]/', $bytes);
        self::assertSame('P' . $gradient->objectNumber, $name);
    }

    public function testRadialGradientRegistersShadingType3(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $gradient = $doc->addRadialGradient(
            new Point(100, 100),
            0.0,
            new Point(100, 100),
            80.0,
            [1, 1, 1],
            [0, 0, 0.4],
        );
        $page->useGradient($gradient);

        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/ShadingType 3', $bytes);
        self::assertMatchesRegularExpression('/\/Coords \[ 100 100 0 100 100 80 \]/', $bytes);
    }

    public function testGradientUsesRgbColorSpace(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $g = $doc->addLinearGradient(new Point(0, 0), new Point(1, 1), [1, 0, 0], [0, 1, 0]);
        $page->useGradient($g);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/ColorSpace /DeviceRGB', $bytes);
    }

    // -----------------------------------------------------------------------
    // 4.13 — Multimedia + 3D annotations
    // -----------------------------------------------------------------------

    public function testAddSoundAnnotationRegistersAndLinks(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $sound = new Sound(44100.0, 'fakesamples');
        $doc->addSoundAnnotation($page, new Rectangle(72, 700, 24, 24), $sound);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Sound', $bytes);
        self::assertStringContainsString('/Type /Sound', $bytes);
        self::assertMatchesRegularExpression('/\/Sound \d+ 0 R/', $bytes);
    }

    public function testAddMovieAnnotationRegistersAndLinks(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $movie = new Movie(new FileSpec('movie.mp4'));
        $doc->addMovieAnnotation($page, new Rectangle(72, 600, 100, 100), $movie);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Movie', $bytes);
        self::assertMatchesRegularExpression('/\/Movie \d+ 0 R/', $bytes);
    }

    public function testAdd3DAnnotationAttachesStream(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $stream = new ThreeDStream('fake-u3d-bytes');
        $doc->add3DAnnotation($page, new Rectangle(72, 400, 200, 200), $stream);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /3D', $bytes);
        self::assertMatchesRegularExpression('/\/3DD \d+ 0 R/', $bytes);
    }

    // -----------------------------------------------------------------------
    // 4.2 — Form field builders
    // -----------------------------------------------------------------------

    public function testAddTextFieldCreatesAcroFormAndWidget(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addTextField('name', $page, new Rectangle(72, 700, 200, 22));
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/AcroForm', $bytes);
        self::assertStringContainsString('/FT /Tx', $bytes);
        self::assertStringContainsString('/T (name)', $bytes);
        self::assertStringContainsString('/Subtype /Widget', $bytes);
        // Widget should be in the page annots
        self::assertNotEmpty($page->corePage()->annots);
    }

    public function testTextFieldOptionsSetFlagsAndMaxLen(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addTextField(
            'address',
            $page,
            new Rectangle(72, 500, 200, 60),
            new TextFieldOptions(
                defaultValue: 'Enter address',
                maxLength: 100,
                multiline: true,
                required: true,
            ),
        );
        $bytes = $doc->writer()->generate();
        // Required + multiline → flags 2 | (1 << 12) = 4098
        self::assertStringContainsString('/Ff 4098', $bytes);
        self::assertStringContainsString('/MaxLen 100', $bytes);
        self::assertStringContainsString('(Enter address)', $bytes);
    }

    public function testAddCheckboxDefaultsToOff(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addCheckbox('agree', $page, new Rectangle(72, 700, 14, 14));
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/FT /Btn', $bytes);
        self::assertStringContainsString('/T (agree)', $bytes);
        self::assertStringContainsString('/V /Off', $bytes);
    }

    public function testAddCheckboxDefaultCheckedUsesOnValue(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addCheckbox(
            'newsletter',
            $page,
            new Rectangle(72, 700, 14, 14),
            new CheckboxOptions(onValue: 'Yes', defaultChecked: true),
        );
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/V /Yes', $bytes);
    }

    public function testAddChoiceFieldComboWithLabels(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addChoiceField(
            'country',
            $page,
            new Rectangle(72, 500, 200, 22),
            new ChoiceFieldOptions(
                choices: [['us', 'United States'], ['ca', 'Canada'], ['mx', 'Mexico']],
                defaultValue: 'us',
                combo: true,
                sort: true,
            ),
        );
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/FT /Ch', $bytes);
        self::assertStringContainsString('(United States)', $bytes);
        // Combo flag = 1<<17 = 131072; sort = 1<<19 = 524288; combined = 655360.
        self::assertStringContainsString('/Ff 655360', $bytes);
    }

    public function testAddSignatureFieldSetsSigFlagsOnForm(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addSignatureField('signature', $page, new Rectangle(72, 100, 200, 60));
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/FT /Sig', $bytes);
        self::assertStringContainsString('/SigFlags 3', $bytes);
    }

    public function testMultipleFieldsShareTheSameAcroForm(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addTextField('name', $page, new Rectangle(72, 700, 200, 22));
        $doc->addCheckbox('agree', $page, new Rectangle(72, 670, 14, 14));
        $doc->addChoiceField('country', $page, new Rectangle(72, 640, 200, 22), new ChoiceFieldOptions(choices: ['US', 'CA']));
        $bytes = $doc->writer()->generate();
        // One AcroForm with three field references in /Fields.
        self::assertSame(1, substr_count($bytes, '/Type /Catalog'));
        self::assertMatchesRegularExpression('/\/Fields \[ \d+ 0 R \d+ 0 R \d+ 0 R \]/', $bytes);
    }

    public function testFieldOptionsRequiredAndReadOnlyFlags(): void
    {
        // Required + readOnly together → bits 1 | 2 = 3
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addTextField(
            'frozen',
            $page,
            new Rectangle(72, 700, 200, 22),
            new TextFieldOptions(required: true, readOnly: true),
        );
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Ff 3', $bytes);
    }
}
