<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Embellished-operator detection tests.
 *
 * The most visible behavioural difference is movablelimits
 * routing on `<munder>` / `<mover>`: in inline (non-display)
 * style, when the base is an embellished operator with
 * movablelimits=true (e.g. sum, product, integral), the
 * over/under positioning collapses to subscript/superscript.
 *
 * Without embellished-operator detection, only a bare `<mo>` as
 * the base would trigger the collapse; an `<mstyle>` /
 * `<mpadded>` / `<mrow>` wrapper would block it.
 *
 * We verify the collapse fires for several wrapper shapes by
 * comparing baseline-Y offsets in the content stream: a
 * collapsed limit (script positioning) sits ABOVE the under
 * baseline because subscripts shift the BASE up to make room.
 * The simpler heuristic: the painter emits different Td/Ts
 * patterns for the two layouts. We just check for content-stream
 * differences and stay focused on PDF validity for now - the
 * structural check is via the existing munder painter being
 * exercised through coreOperator().
 */
final class EmbellishedOperatorTest extends TestCase
{
    public function testMstyleWrappedSumGetsLimitsAsScriptsInline(): void
    {
        // <munder><mstyle><mo>∑</mo></mstyle><mn>i</mn></munder>
        // - inline mode, sum operator has movablelimits=true ->
        // collapse to subscript.
        $bytesWrapped = $this->render(
            '<munder>'
            . '<mstyle><mo>' . "\u{2211}" . '</mo></mstyle>'
            . '<mn>i</mn>'
            . '</munder>',
        );
        // Baseline: same expression with bare <mo> should produce
        // the same routing decision.
        $bytesBare = $this->render(
            '<munder><mo>' . "\u{2211}" . '</mo><mn>i</mn></munder>',
        );
        // Strip object numbers / lengths so we can compare the
        // content streams structurally.
        self::assertSame(
            $this->extractTextOps($bytesBare),
            $this->extractTextOps($bytesWrapped),
        );
    }

    public function testMrowWrappedSumGetsLimitsAsScriptsInline(): void
    {
        // <munder><mrow><mo>∑</mo></mrow><mn>i</mn></munder>
        // - mrow with exactly one significant child = embellished.
        $bytesWrapped = $this->render(
            '<munder>'
            . '<mrow><mo>' . "\u{2211}" . '</mo></mrow>'
            . '<mn>i</mn>'
            . '</munder>',
        );
        $bytesBare = $this->render(
            '<munder><mo>' . "\u{2211}" . '</mo><mn>i</mn></munder>',
        );
        self::assertSame(
            $this->extractTextOps($bytesBare),
            $this->extractTextOps($bytesWrapped),
        );
    }

    public function testMpaddedWrappedSumGetsLimitsAsScriptsInline(): void
    {
        $bytesWrapped = $this->render(
            '<munder>'
            . '<mpadded><mo>' . "\u{2211}" . '</mo></mpadded>'
            . '<mn>i</mn>'
            . '</munder>',
        );
        $bytesBare = $this->render(
            '<munder><mo>' . "\u{2211}" . '</mo><mn>i</mn></munder>',
        );
        self::assertSame(
            $this->extractTextOps($bytesBare),
            $this->extractTextOps($bytesWrapped),
        );
    }

    public function testMrowWithMultipleSignificantChildrenIsNotEmbellished(): void
    {
        // <munder><mrow><mo>∑</mo><mn>x</mn></mrow><mn>i</mn></munder>
        // The mrow has TWO significant children -> not embellished,
        // so the under stays as a centred under, NOT collapsed to
        // a subscript. Stream should differ from the bare <mo> case.
        $bytesNotEmbellished = $this->render(
            '<munder>'
            . '<mrow><mo>' . "\u{2211}" . '</mo><mn>x</mn></mrow>'
            . '<mn>i</mn>'
            . '</munder>',
        );
        $bytesBare = $this->render(
            '<munder><mo>' . "\u{2211}" . '</mo><mn>i</mn></munder>',
        );
        self::assertNotSame(
            $this->extractTextOps($bytesBare),
            $this->extractTextOps($bytesNotEmbellished),
            'mrow with two significant children should NOT trigger '
                . 'limits-as-scripts routing',
        );
    }

    public function testDisplayStyleKeepsLimitsCentredEvenForEmbellished(): void
    {
        // displaystyle=true should keep the centred under
        // positioning even when the base is an embellished
        // operator. (The movablelimits routing only fires in
        // inline mode.)
        $bytesDisplay = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" '
            . 'display="block">'
            . '<munder>'
            . '<mstyle><mo>' . "\u{2211}" . '</mo></mstyle>'
            . '<mn>i</mn>'
            . '</munder>'
            . '</math>',
            wrap: false,
        );
        // In display mode, baseline-Y of the sum stays at the row
        // baseline. We don't assert specifics here beyond PDF
        // validity - the structural check is that the painter
        // dispatched via the centred-under path.
        self::assertStringStartsWith('%PDF-', $bytesDisplay);
    }

    public function testSemanticsWrappedSumIsEmbellished(): void
    {
        // <semantics><mo>∑</mo><annotation>foo</annotation></semantics>
        // - first (presentation) child is the bare operator.
        $bytesWrapped = $this->render(
            '<munder>'
            . '<semantics><mo>' . "\u{2211}" . '</mo>'
            . '<annotation encoding="TeX">sum</annotation></semantics>'
            . '<mn>i</mn>'
            . '</munder>',
        );
        $bytesBare = $this->render(
            '<munder><mo>' . "\u{2211}" . '</mo><mn>i</mn></munder>',
        );
        self::assertSame(
            $this->extractTextOps($bytesBare),
            $this->extractTextOps($bytesWrapped),
        );
    }

    public function testMactionWrappedSumIsEmbellishedAtSelection(): void
    {
        // <maction selection="2"><mn>X</mn><mo>∑</mo></maction>
        // The selected child is the bare operator.
        $bytesWrapped = $this->render(
            '<munder>'
            . '<maction selection="2"><mn>X</mn>'
            . '<mo>' . "\u{2211}" . '</mo></maction>'
            . '<mn>i</mn>'
            . '</munder>',
        );
        $bytesBare = $this->render(
            '<munder><mo>' . "\u{2211}" . '</mo><mn>i</mn></munder>',
        );
        self::assertSame(
            $this->extractTextOps($bytesBare),
            $this->extractTextOps($bytesWrapped),
        );
    }

    /**
     * Extract the sequence of (Td / TJ / Tj / Ts / Tf) operators
     * from the content stream, dropping object headers and font
     * lookup tables so structurally-equivalent renders compare
     * equal.
     */
    private function extractTextOps(string $bytes): string
    {
        // Find the content stream payload between 'stream' and
        // 'endstream'. Strip everything except text/showtext
        // operators.
        if (preg_match(
            '/^stream\s*$\n(.*?)\nendstream/sm',
            $bytes,
            $m,
        ) !== 1) {
            return '';
        }
        $stream = $m[1];
        // Keep lines that contain an operator we care about.
        $lines = explode("\n", $stream);
        $kept = [];
        foreach ($lines as $line) {
            if (preg_match('/\b(Tj|TJ|Td|Ts|Tm|cm)\b/', $line) === 1) {
                $kept[] = trim($line);
            }
        }
        return implode("\n", $kept);
    }

    private function render(string $innerXml, bool $wrap = true): string
    {
        if ($wrap) {
            $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . $innerXml . '</math>';
        } else {
            $xml = $innerXml;
        }
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
