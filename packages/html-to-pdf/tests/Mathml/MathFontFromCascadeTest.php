<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Mathml;

use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * A MATH-table font resolved through the CSS cascade has to reach the
 * MathML painter.
 *
 * `Painter::mathmlRendererFor()` builds a renderer carrying the
 * `@font-face`-loaded {@see \Phpdftk\FontParser\OpenTypeData} for the
 * `<math>` element's resolved family, so the font's MathConstants
 * (FractionRuleThickness, AxisHeight, the script shifts, ...) drive
 * layout. It existed but was never called: `paintInlineMath` used the
 * cached default renderer, which has no font at all, so every document
 * laid its maths out on the tracer-bullet constants no matter what the
 * author specified.
 *
 * The probe is the fraction bar, because it is the one MATH constant
 * that reaches the content stream as a single readable number: a
 * `w` (set-line-width) operator.
 */
final class MathFontFromCascadeTest extends TestCase
{
    /**
     * WPT ships single-purpose MATH fonts whose constants are encoded
     * in the filename. This one sets FractionRuleThickness to 1000 on
     * a 1000-unit em, i.e. exactly 1em, so at `font-size: 20px` the
     * bar must be 20 units thick - impossible to confuse with the
     * 0.0625em tracer-bullet default, which gives 1.25.
     */
    private const string MATH_FONT =
        'fraction-denominatordisplaystylegapmin5000-rulethickness1000.woff';

    private const float FONT_SIZE = 20.0;

    public function testFractionRuleThicknessComesFromTheCascadedMathFont(): void
    {
        self::assertEqualsWithDelta(
            self::FONT_SIZE,
            $this->fractionBarThickness($this->renderWithMathFont()),
            0.01,
            'the bar must use the font\'s FractionRuleThickness (1em), not the 0.0625em default',
        );
    }

    public function testWithoutAMathFontTheDefaultConstantsStillApply(): void
    {
        // The guard for the other direction: no MATH font in the
        // cascade must leave the historical tracer-bullet metrics
        // alone rather than dropping the bar or erroring.
        self::assertEqualsWithDelta(
            0.0625 * self::FONT_SIZE,
            $this->fractionBarThickness($this->renderWithoutMathFont()),
            0.01,
        );
    }

    public function testANonMathFontInTheCascadeIsNotMistakenForOne(): void
    {
        // A perfectly good font with no MATH table must not be handed
        // to the math renderer as though it had one.
        $bytes = $this->render(
            '<style>math { font-family: serif; font-size: 20px; }</style>'
            . $this->mathMarkup(),
        );
        self::assertEqualsWithDelta(
            0.0625 * self::FONT_SIZE,
            $this->fractionBarThickness($bytes),
            0.01,
        );
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function renderWithMathFont(): string
    {
        return $this->render(
            '<style>'
            . '@font-face { font-family: wptmath; src: url("' . self::MATH_FONT . '"); }'
            . 'math { font-family: wptmath; font-size: ' . self::FONT_SIZE . 'px; }'
            . '</style>'
            . $this->mathMarkup(),
        );
    }

    private function renderWithoutMathFont(): string
    {
        return $this->render(
            '<style>math { font-size: ' . self::FONT_SIZE . 'px; }</style>'
            . $this->mathMarkup(),
        );
    }

    private function mathMarkup(): string
    {
        return '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">'
            . '<mfrac><mn>1</mn><mn>2</mn></mfrac></math>';
    }

    /** The `w` operand of the emitted fraction rule. */
    private function fractionBarThickness(string $bytes): float
    {
        preg_match_all('/([\d.]+)\s+w\b/', $bytes, $matches);
        self::assertNotEmpty($matches[1], 'no fraction bar was stroked');

        return (float) $matches[1][count($matches[1]) - 1];
    }

    private function render(string $head): string
    {
        $fontsDir = __DIR__ . '/../../../../vendor-data/wpt/fonts/math';
        if (!is_file($fontsDir . '/' . self::MATH_FONT)) {
            self::markTestSkipped(
                'WPT math fonts not available. '
                . 'Run `git submodule update --init vendor-data/wpt`.',
            );
        }
        $writer = new PdfWriter(compressStreams: false);
        $options = (new RendererOptions())
            ->withBaseDir($fontsDir)
            ->withSandboxRoot($fontsDir);
        $uaFont = __DIR__ . '/../../../wpt-harness/resources/fonts/DejaVuSerif.ttf';
        if (is_file($uaFont)) {
            $options = $options->withDefaultFont((new TrueTypeParser($uaFont))->parse());
        }
        (new Renderer($options))->renderInto(
            $writer,
            '<html><head>' . $head . '</head><body></body></html>',
        );

        return $writer->toBytes();
    }
}
