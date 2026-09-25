<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * What the painter DRAWS and what the measurer REPORTS have to agree
 * for every container-shaped element.
 *
 * The painter treats a set of elements as transparent groups:
 * `<semantics>` renders only its first (presentation) child per Core
 * §5.1, `<maction>` renders its `selection`-indexed child per §3.6.1,
 * `<merror>` and `<mlabeledtr>` pass their children through. The
 * measurement helpers had their own, shorter list of container shapes,
 * so anything missing from it fell through to the "flatten the text
 * content" fallback and reported a size unrelated to the ink.
 *
 * That desync was invisible while the intrinsic size was only consulted
 * by the painter for its own box: both sides of a reftest measured
 * wrong identically. It became load-bearing once layout started sizing
 * the `<math>` box from the same numbers.
 *
 * The identity each case asserts is the one the WPT reftests assert:
 * wrapping content in a transparent container must not change its size
 * or position.
 */
final class ContainerMeasurementTest extends TestCase
{
    private const float ORIGIN_X = 100.0;

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function transparentWrappers(): iterable
    {
        $content = '<mspace width="40px" height="20px" depth="5px" mathbackground="blue"/>';
        yield 'semantics' => ["<semantics>$content</semantics>", $content];
        yield 'semantics with annotation' => [
            "<semantics>$content<annotation encoding=\"text/plain\">x</annotation></semantics>",
            $content,
        ];
        yield 'merror' => ["<merror>$content</merror>", $content];
        yield 'maction' => ["<maction>$content</maction>", $content];
        yield 'maction selection 2' => [
            '<maction selection="2"><mspace width="400px" height="200px"/>'
            . $content . '</maction>',
            $content,
        ];
        yield 'mrow' => ["<mrow>$content</mrow>", $content];
        yield 'mstyle' => ["<mstyle>$content</mstyle>", $content];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('transparentWrappers')]
    public function testWrappingInATransparentContainerDoesNotChangeTheMeasuredSize(
        string $wrapped,
        string $bare,
    ): void {
        self::assertEqualsWithDelta(
            $this->intrinsicSize($bare),
            $this->intrinsicSize($wrapped),
            0.01,
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('transparentWrappers')]
    public function testWrappingInATransparentContainerDoesNotMoveTheInk(
        string $wrapped,
        string $bare,
    ): void {
        self::assertEqualsWithDelta(
            $this->inkRect($bare),
            $this->inkRect($wrapped),
            0.01,
        );
    }

    public function testAnnotationContentIsNotMeasured(): void
    {
        // A long annotation must not widen the equation: it is not
        // visual content, and the painter never draws it.
        $short = $this->intrinsicSize(
            '<semantics><mn>1</mn><annotation encoding="text/plain">x</annotation></semantics>',
        );
        $long = $this->intrinsicSize(
            '<semantics><mn>1</mn>'
            . '<annotation encoding="text/plain">a very much longer annotation</annotation>'
            . '</semantics>',
        );
        self::assertEqualsWithDelta($short, $long, 0.01);
    }

    /**
     * Intrinsic [width, height] the html-to-pdf layer sizes the box
     * with.
     *
     * @return array{0: float, 1: float}
     */
    private function intrinsicSize(string $innerXml): array
    {
        $writer = new PdfWriter(compressStreams: false);
        $renderer = new MathmlRenderer($writer->addPage(), $writer);
        $document = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $innerXml . '</math>',
        );

        return $renderer->intrinsicSize($document, 16.0);
    }

    /**
     * The `mathbackground` rectangle the content paints, as
     * `[x, y, w, h]` — where the ink actually lands.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function inkRect(string $innerXml): array
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $document = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $innerXml . '</math>',
        );
        $renderer->draw($document, self::ORIGIN_X, 600.0, 300.0, 120.0, fontSize: 16.0);
        $bytes = $writer->toBytes();
        preg_match_all(
            '/(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+re\b/',
            $bytes,
            $matches,
            PREG_SET_ORDER,
        );
        self::assertNotEmpty($matches, 'no mathbackground rectangle was painted');
        $last = $matches[count($matches) - 1];

        return [(float) $last[1], (float) $last[2], (float) $last[3], (float) $last[4]];
    }
}
