<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Font\Encoding;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Font\Type3Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Writer\PdfWriter;

// A Type 3 font lets you ship arbitrary PDF content streams as glyphs — perfect for
// pictograms, custom dingbats, or any vector mark that should behave like text.
$writer = new PdfWriter();
$page = $writer->addPage();
$caption = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// Glyph 'square' — a filled square spanning the 700×700 glyph space.
$squareProc = new ContentStream();
$squareProc
    ->setGlyphWidthAndBoundingBox(700, 0, 0, 0, 700, 700)
    ->rectangle(100, 100, 500, 500)
    ->fill();

// Glyph 'triangle' — a filled equilateral-ish triangle.
$triangleProc = new ContentStream();
$triangleProc
    ->setGlyphWidthAndBoundingBox(700, 0, 0, 0, 700, 700)
    ->moveTo(350, 650)
    ->lineTo(50, 100)
    ->lineTo(650, 100)
    ->closePath()
    ->fill();

// Glyph 'star' — a five-point star, painted with a single subpath.
$starProc = new ContentStream();
$starProc
    ->setGlyphWidthAndBoundingBox(700, 0, 0, 0, 700, 700)
    ->moveTo(350, 660)
    ->lineTo(431, 446)
    ->lineTo(660, 437)
    ->lineTo(478, 297)
    ->lineTo(553, 80)
    ->lineTo(350, 200)
    ->lineTo(147, 80)
    ->lineTo(222, 297)
    ->lineTo(40, 437)
    ->lineTo(269, 446)
    ->closePath()
    ->fill();

$squareRef   = $writer->register($squareProc);
$triangleRef = $writer->register($triangleProc);
$starRef     = $writer->register($starProc);

// Encoding: map ASCII 'A','B','C' to the three glyph names.
$encoding = new Encoding();
$encoding->differences = new PdfArray([
    new PdfNumber(65),
    new PdfName('square'),
    new PdfName('triangle'),
    new PdfName('star'),
]);
$encodingRef = $writer->register($encoding);

$font = new Type3Font('DemoType3');
$font->fontBBox = new PdfArray([
    new PdfNumber(0), new PdfNumber(0), new PdfNumber(700), new PdfNumber(700),
]);
$font->firstChar = 65;
$font->lastChar  = 67;
$font->widths = new PdfArray([
    new PdfNumber(700), new PdfNumber(700), new PdfNumber(700),
]);
$font->encoding = $encodingRef;
$font->addCharProc('square',   $squareRef);
$font->addCharProc('triangle', $triangleRef);
$font->addCharProc('star',     $starRef);
$font->resources = new PdfDictionary(['ProcSet' => new PdfArray([new PdfName('PDF')])]);

$customName = $writer->addFont($font)->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($caption, 22)->moveTextPosition(72, 740)
    ->showText('Type 3 custom glyphs')->endText();

$cs->beginText()->setFont($caption, 11)->moveTextPosition(72, 700)
    ->showText('A, B and C have been remapped to a square, triangle and star.')
    ->endText();

// Paint "ABC" at 96pt.
$cs->beginText()->setFont($customName, 96)->moveTextPosition(72, 540)
    ->showText('ABC')->endText();

// Mixed with regular text on the next line.
$cs->beginText()->setFont($customName, 32)->moveTextPosition(72, 460)
    ->showText('AAA BBB CCC')->endText();

$writer->save('type3-custom.pdf');
// endregion

rename(__DIR__ . '/type3-custom.pdf', example_output_path('core/fonts/type3-custom.pdf'));
