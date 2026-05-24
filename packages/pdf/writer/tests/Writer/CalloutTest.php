<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\CalloutStyle;
use Phpdftk\Pdf\Writer\CalloutType;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\Theme;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class CalloutTest extends TestCase
{
    use QpdfValidationTrait;

    public function testNoteCalloutEmitsLabelAndBody(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addCallout('Heads up — there is a thing to know.', CalloutType::Note);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Note', $bytes);
        self::assertStringContainsString('Heads up', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testEachTypeUsesItsOwnBarColor(): void
    {
        // Render four callouts in one doc, each in a different type;
        // we should see the four distinct bar colours in the stream.
        $pdf = new Pdf(compressStreams: false);
        $pdf->addCallout('Body', CalloutType::Note);
        $pdf->addCallout('Body', CalloutType::Tip);
        $pdf->addCallout('Body', CalloutType::Warning);
        $pdf->addCallout('Body', CalloutType::Danger);

        $bytes = $pdf->toBytes();
        // Blue (Note) bar fill — 0.23 0.51 0.96 rg
        self::assertMatchesRegularExpression('/0\.23 0\.51 0\.96 rg/', $bytes);
        // Green (Tip)
        self::assertMatchesRegularExpression('/0\.13 0\.6 0\.35 rg/', $bytes);
        // Amber (Warning)
        self::assertMatchesRegularExpression('/0\.92 0\.61 0\.1 rg/', $bytes);
        // Red (Danger)
        self::assertMatchesRegularExpression('/0\.86 0\.2 0\.2 rg/', $bytes);
    }

    public function testCalloutEmitsBackgroundFillRectangle(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addCallout('Body', CalloutType::Warning);

        $bytes = $pdf->toBytes();
        // Background tint comes first; amber-50 ≈ 1 0.97 0.92 rg
        self::assertMatchesRegularExpression('/1 0\.97 0\.92 rg/', $bytes);
        // Rectangle + fill operators present
        self::assertMatchesRegularExpression('/\d+ \d+(?:\.\d+)? \d+ \d+(?:\.\d+)? re\s+f/', $bytes);
    }

    public function testShowLabelFalseSkipsTitleRow(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addCallout(
            'Body without title',
            CalloutType::Note,
            new CalloutStyle(showLabel: false),
        );

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Body without title', $bytes);
        // No "Note" label rendered as a separate text run.
        // (It might still appear inside the body if the body contained the word, but our
        // body string doesn't — so absence is a clean assertion.)
        self::assertStringNotContainsString('(Note)', $bytes);
    }

    public function testLabelOverrideReplacesDefaultLabel(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addCallout(
            'Body',
            CalloutType::Note,
            new CalloutStyle(labelOverride: 'Heads up'),
        );

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(Heads up)', $bytes);
        self::assertStringNotContainsString('(Note)', $bytes);
    }

    public function testStyleOverridesBarAndBackgroundColors(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addCallout(
            'Body',
            CalloutType::Note,
            new CalloutStyle(
                barColor: [0.5, 0.0, 0.5],
                bgColor: [0.95, 0.9, 0.95],
            ),
        );

        $bytes = $pdf->toBytes();
        self::assertMatchesRegularExpression('/0\.5 0 0\.5 rg/', $bytes);
        self::assertMatchesRegularExpression('/0\.95 0\.9 0\.95 rg/', $bytes);
    }

    public function testCalloutAutoBreaksToNewPageWhenItWontFit(): void
    {
        // Force the cursor near the bottom by adding lots of text first.
        $pdf = new Pdf(compressStreams: false);
        for ($i = 0; $i < 50; $i++) {
            $pdf->addText('Filler line ' . $i);
        }
        $pdf->addCallout(
            "Multi-line callout body\nthat would not fit at the bottom of the previous page.",
            CalloutType::Tip,
        );

        $bytes = $pdf->toBytes();
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "/Type /Page\n"));
    }

    public function testCalloutTallerThanPageThrows(): void
    {
        $pdf = new Pdf(compressStreams: false);
        // Construct a callout body so long it can't fit even on a fresh page.
        $hugeBody = str_repeat('Word ', 5000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not split callouts');
        $pdf->addCallout($hugeBody, CalloutType::Note);
    }

    public function testWriterPageDrawCalloutRendersPositioned(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $page = $pdf->doc()->addPage();
        $body = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));
        $bold = $pdf->writer()->addFont(new Type1Font(StandardFont::HelveticaBold));

        $height = $page->drawCallout(
            'Body text inside a positioned callout.',
            72.0,
            720.0,
            400.0,
            CalloutType::Tip,
            $body,
            $bold,
            size: 11.0,
        );

        self::assertGreaterThan(0.0, $height);
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Body text inside', $bytes);
        // Tip green bar
        self::assertMatchesRegularExpression('/0\.13 0\.6 0\.35 rg/', $bytes);
    }

    public function testThemeColorIsUsedForBodyTextWhenStyleHasNoOverride(): void
    {
        $theme = new Theme(color: [0.2, 0.2, 0.5]);
        $pdf = new Pdf(theme: $theme, compressStreams: false);
        $pdf->addCallout('Coloured body', CalloutType::Note);

        $bytes = $pdf->toBytes();
        self::assertMatchesRegularExpression('/0\.2 0\.2 0\.5 rg/', $bytes);
    }

    public function testCalloutTypeDefaultLabelMatchesEnumValue(): void
    {
        self::assertSame('Note', CalloutType::Note->defaultLabel());
        self::assertSame('Tip', CalloutType::Tip->defaultLabel());
        self::assertSame('Warning', CalloutType::Warning->defaultLabel());
        self::assertSame('Danger', CalloutType::Danger->defaultLabel());
    }
}
