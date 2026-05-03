<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Document\PageLabel;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;

/**
 * Generates a PDF with page labels (numbering schemes) and verifies validity.
 */
#[Group("qpdf")]
class PageLabelsTest extends TestCase
{
    use QpdfValidationTrait;
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/page_labels.pdf';

    public function testGeneratesPageLabelsPdf(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        $boldName = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

        // Front matter: 3 pages with lowercase roman numerals
        for ($i = 1; $i <= 3; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Front Matter — page %s', $this->toRoman($i)))
               ->moveTextPosition(0, -20)
               ->showText('Table of contents, preface, or acknowledgements.')
               ->endText();

            // Page number at bottom center
            $cs->beginText()
               ->setFont($fontName, 10)
               ->moveTextPosition(296, 36)
               ->showText($this->toRoman($i))
               ->endText();
        }

        // Body: 5 pages with arabic numerals
        for ($i = 1; $i <= 5; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($boldName, 16)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Chapter %d', $i))
               ->endText();

            $cs->beginText()
               ->setFont($fontName, 11)
               ->moveTextPosition(72, 690)
               ->showText('Lorem ipsum dolor sit amet, consectetur adipiscing elit.')
               ->moveTextPosition(0, -16)
               ->showText('Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.')
               ->endText();

            // Page number at bottom center
            $cs->beginText()
               ->setFont($fontName, 10)
               ->moveTextPosition(296, 36)
               ->showText((string) $i)
               ->endText();
        }

        // Appendix: 2 pages with uppercase alpha and prefix
        for ($i = 0; $i < 2; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($boldName, 14)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Appendix %s', chr(65 + $i)))
               ->endText();

            $cs->beginText()
               ->setFont($fontName, 11)
               ->moveTextPosition(72, 690)
               ->showText('Supplementary material and references.')
               ->endText();

            $cs->beginText()
               ->setFont($fontName, 10)
               ->moveTextPosition(290, 36)
               ->showText('App-' . chr(65 + $i))
               ->endText();
        }

        // Set page label ranges
        $frontMatter = new PageLabel();
        $frontMatter->s = new PdfName('r'); // lowercase roman

        $body = new PageLabel();
        $body->s = new PdfName('D'); // arabic

        $appendix = new PageLabel();
        $appendix->s = new PdfName('A'); // uppercase alpha
        $appendix->p = new PdfString('App-');

        $writer->setPageLabels([
            0 => $frontMatter,
            3 => $body,
            8 => $appendix,
        ]);

        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('/Type /PageLabel', $content);
        self::assertStringContainsString('/S /r', $content);
        self::assertStringContainsString('/S /D', $content);
        self::assertStringContainsString('/S /A', $content);
        self::assertStringContainsString('/P (App-)', $content);
        self::assertStringContainsString('/PageLabels', $content);
        self::assertStringContainsString('%%EOF', $content);
    }

    private function toRoman(int $num): string
    {
        $map = ['m' => 1000, 'cm' => 900, 'd' => 500, 'cd' => 400, 'c' => 100, 'xc' => 90, 'l' => 50, 'xl' => 40, 'x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1];
        $result = '';
        foreach ($map as $roman => $value) {
            while ($num >= $value) {
                $result .= $roman;
                $num -= $value;
            }
        }
        return $result;
    }
}
