<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\FontParser\MathConstants;
use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlMetrics;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `<mfrac displaystyle="true">` — display-style fractions
 * use taller numerator/denominator shifts (per MathConstants'
 * `fractionNumeratorDisplayStyleShiftUp` /
 * `fractionDenominatorDisplayStyleShiftDown`) than inline fractions.
 *
 * Two surfaces:
 *
 *   1. MathmlMetrics adapter — the accessor picks the right
 *      MathConstants field based on the displayStyle flag.
 *   2. Translator — paintMfrac reads `<mfrac displaystyle>` and
 *      passes the flag to the metrics accessor, producing
 *      different Td shifts in the rendered PDF.
 */
final class MfracDisplayStyleTest extends TestCase
{
    public function testMetricsAccessorDefaultsToInline(): void
    {
        // Default-call (no arg) returns inline shift.
        $m = new MathmlMetrics();
        self::assertSame(
            MathmlMetrics::DEFAULT_FRACTION_NUMERATOR_SHIFT_UP_EM,
            $m->fractionNumeratorShiftUpEm(),
        );
    }

    public function testMetricsAccessorPicksDisplayStyleDefault(): void
    {
        // Explicit displayStyle=true returns the larger default.
        $m = new MathmlMetrics();
        self::assertSame(
            MathmlMetrics::DEFAULT_FRACTION_NUMERATOR_DISPLAY_SHIFT_UP_EM,
            $m->fractionNumeratorShiftUpEm(displayStyle: true),
        );
        self::assertSame(
            MathmlMetrics::DEFAULT_FRACTION_DENOMINATOR_DISPLAY_SHIFT_DOWN_EM,
            $m->fractionDenominatorShiftDownEm(displayStyle: true),
        );
    }

    public function testMetricsAccessorReadsDisplayStyleConstantWhenLoaded(): void
    {
        // When a math font is loaded, the accessor selects the
        // *display* field from MathConstants rather than the inline.
        $constants = $this->makeConstants([
            'fractionNumeratorShiftUp' => 400,
            'fractionNumeratorDisplayStyleShiftUp' => 700,
            'fractionDenominatorShiftDown' => 400,
            'fractionDenominatorDisplayStyleShiftDown' => 700,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertSame(0.4, $m->fractionNumeratorShiftUpEm());
        self::assertSame(0.7, $m->fractionNumeratorShiftUpEm(displayStyle: true));
        self::assertSame(0.4, $m->fractionDenominatorShiftDownEm());
        self::assertSame(0.7, $m->fractionDenominatorShiftDownEm(displayStyle: true));
    }

    public function testPaintMfracDisplayStyleProducesDifferentTds(): void
    {
        // Default fraction vs displaystyle="true": the same content
        // renders with different vertical shifts, so the Td sequence
        // in the content stream diverges.
        $inline = $this->renderMfrac('');
        $display = $this->renderMfrac(' displaystyle="true"');
        self::assertNotSame(
            $this->extractTds($inline),
            $this->extractTds($display),
            'displaystyle should shift numerator + denominator differently',
        );
    }

    public function testPaintMfracExplicitFalseRendersAsInline(): void
    {
        // `displaystyle="false"` is the default behaviour - identical
        // to omitting the attribute.
        $omit = $this->renderMfrac('');
        $explicit = $this->renderMfrac(' displaystyle="false"');
        self::assertSame(
            $this->extractTds($omit),
            $this->extractTds($explicit),
        );
    }

    private function renderMfrac(string $extraAttrs): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mfrac' . $extraAttrs . '><mn>1</mn><mn>2</mn></mfrac>'
            . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }

    /**
     * @return list<array{float, float}>
     */
    private function extractTds(string $bytes): array
    {
        if (!preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Td\b/', $bytes, $matches)) {
            return [];
        }
        $out = [];
        foreach ($matches[1] as $i => $dx) {
            $out[] = [(float) $dx, (float) $matches[2][$i]];
        }
        return $out;
    }

    /**
     * @param array<string, int> $overrides
     */
    private function makeConstants(array $overrides): MathConstants
    {
        $defaults = array_fill_keys([
            'scriptPercentScaleDown', 'scriptScriptPercentScaleDown',
            'delimitedSubFormulaMinHeight', 'displayOperatorMinHeight',
            'mathLeading', 'axisHeight', 'accentBaseHeight', 'flattenedAccentBaseHeight',
            'subscriptShiftDown', 'subscriptTopMax', 'subscriptBaselineDropMin',
            'superscriptShiftUp', 'superscriptShiftUpCramped', 'superscriptBottomMin',
            'superscriptBaselineDropMax', 'subSuperscriptGapMin',
            'superscriptBottomMaxWithSubscript', 'spaceAfterScript',
            'upperLimitGapMin', 'upperLimitBaselineRiseMin',
            'lowerLimitGapMin', 'lowerLimitBaselineDropMin',
            'stackTopShiftUp', 'stackTopDisplayStyleShiftUp',
            'stackBottomShiftDown', 'stackBottomDisplayStyleShiftDown',
            'stackGapMin', 'stackDisplayStyleGapMin',
            'stretchStackTopShiftUp', 'stretchStackBottomShiftDown',
            'stretchStackGapAboveMin', 'stretchStackGapBelowMin',
            'fractionNumeratorShiftUp', 'fractionNumeratorDisplayStyleShiftUp',
            'fractionDenominatorShiftDown', 'fractionDenominatorDisplayStyleShiftDown',
            'fractionNumeratorGapMin', 'fractionNumDisplayStyleGapMin',
            'fractionRuleThickness', 'fractionDenominatorGapMin',
            'fractionDenomDisplayStyleGapMin',
            'skewedFractionHorizontalGap', 'skewedFractionVerticalGap',
            'overbarVerticalGap', 'overbarRuleThickness', 'overbarExtraAscender',
            'underbarVerticalGap', 'underbarRuleThickness', 'underbarExtraDescender',
            'radicalVerticalGap', 'radicalDisplayStyleVerticalGap',
            'radicalRuleThickness', 'radicalExtraAscender',
            'radicalKernBeforeDegree', 'radicalKernAfterDegree',
            'radicalDegreeBottomRaisePercent',
        ], 0);
        $defaults['scriptPercentScaleDown'] = 70;
        $defaults['scriptScriptPercentScaleDown'] = 55;
        $merged = array_merge($defaults, $overrides);
        return new MathConstants(...$merged);
    }
}
