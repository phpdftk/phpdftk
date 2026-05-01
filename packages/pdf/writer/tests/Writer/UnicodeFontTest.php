<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer\Tests\Writer;

use ApprLabs\FontParser\TrueTypeParser;
use ApprLabs\Pdf\Core\Font\Type0Font;
use ApprLabs\Pdf\Core\Font\Type0FontFactory;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class UnicodeFontTest extends TestCase
{
    use QpdfValidationTrait;

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
        $this->markTestSkipped('No TTF font found');
    }

    public function testType0FontFactoryCreatesValidStack(): void
    {
        $data = (new TrueTypeParser($this->findFont()))->parse();
        $codepoints = array_map('mb_ord', str_split('Hello World'));

        [$type0Font, $objects, $fontStream, $descriptor, $cidFont, $toUnicode] =
            Type0FontFactory::fromTrueTypeData($data, $codepoints);

        self::assertInstanceOf(Type0Font::class, $type0Font);
        self::assertNotNull($type0Font->encoding);
        self::assertNotEmpty($objects);
    }

    public function testAddCompositeFontGeneratesValidPdf(): void
    {
        $fontPath = $this->findFont();
        $data = (new TrueTypeParser($fontPath))->parse();

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);

        $text = 'Hello';
        $codepoints = [];
        $hexString = '';
        for ($i = 0; $i < mb_strlen($text); $i++) {
            $char = mb_substr($text, $i, 1);
            $cp = mb_ord($char);
            $codepoints[] = $cp;
            $gid = $data->fullUnicodeToGid[$cp] ?? 0;
            $hexString .= sprintf('%04X', $gid);
        }

        $fontHandle = $writer->addCompositeFont($data, $codepoints);
        $fontName = $fontHandle->getResourceName();

        self::assertStringStartsWith('F', $fontName);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont($fontName, 24)
           ->moveTextPosition(72, 700)
           ->showTextHex($hexString)
           ->endText();

        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertStringContainsString('/Type0', $pdf);
        self::assertStringContainsString('/Identity-H', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testAddCompositeFontSavesToFile(): void
    {
        $fontPath = $this->findFont();
        $data = (new TrueTypeParser($fontPath))->parse();

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);

        $text = 'Unicode Test ABC 123';
        $codepoints = [];
        $hexString = '';
        for ($i = 0; $i < mb_strlen($text); $i++) {
            $cp = mb_ord(mb_substr($text, $i, 1));
            $codepoints[] = $cp;
            $gid = $data->fullUnicodeToGid[$cp] ?? 0;
            $hexString .= sprintf('%04X', $gid);
        }

        $fontHandle = $writer->addCompositeFont($data, $codepoints);
        $fontName = $fontHandle->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont($fontName, 18)
           ->moveTextPosition(72, 720)
           ->showTextHex($hexString)
           ->endText();

        $outputDir = dirname(__DIR__, 4) . '/core/tests/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $path = $outputDir . '/unicode_font_test.pdf';
        $writer->save($path);

        self::assertFileExists($path);
        self::assertStringStartsWith('%PDF', file_get_contents($path));
        $this->assertQpdfValid($path);
    }

    public function testCompositeFontAppearsInFontList(): void
    {
        $fontPath = $this->findFont();
        $data = (new TrueTypeParser($fontPath))->parse();

        $writer = new PdfWriter();
        $writer->addPage();

        $fontHandle = $writer->addCompositeFont($data, [65, 66, 67]);
        $fontName = $fontHandle->getResourceName();
        $fonts = $writer->getFonts();

        self::assertArrayHasKey($fontName, $fonts);
        self::assertInstanceOf(Type0Font::class, $fonts[$fontName]);
    }

    public function testShowTextHexOperator(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $cs = $writer->addContentStream($page);

        $cs->beginText()
           ->showTextHex('00410042')
           ->endText();

        $operators = $cs->getOperators();
        self::assertContains('<00410042> Tj', $operators);
    }

    public function testCompositeFontPerPage(): void
    {
        $fontPath = $this->findFont();
        $data = (new TrueTypeParser($fontPath))->parse();

        $writer = new PdfWriter();
        $page1 = $writer->addPage();
        $page2 = $writer->addPage();

        // Add font to only page1
        $fontHandle = $writer->addCompositeFont($data, [65, 66], $page1);
        $fontName = $fontHandle->getResourceName();

        $cs = $writer->addContentStream($page1);
        $cs->beginText()
           ->setFont($fontName, 12)
           ->moveTextPosition(72, 700)
           ->showTextHex('00410042')
           ->endText();

        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }
}
