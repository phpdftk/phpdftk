<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\Value\Transform;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * CSS Transforms 1 §11 — "if the transform function list is
 * non-invertible the element is not rendered". Applied to a paint
 * server (SVG 2 §13.6.5 `gradientTransform`, §13.3 `patternTransform`),
 * that makes the SERVER invalid, so the referencing element falls back
 * to the `<paint>` fallback colour.
 *
 * We baked the singular matrix straight into the PDF pattern's
 * `/Matrix`, which is undefined behaviour: Ghostscript silently paints
 * nothing, so the shape came out blank instead of green.
 */
final class NonInvertiblePaintServerTest extends TestCase
{
    private function paint(string $body): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">' . $body . '</svg>',
        );
        (new Translator())->paint($doc, $stream, $page, $writer);
        return implode("\n", $stream->getOperators());
    }

    private const string GREEN = '0 0.5019607843 0 rg';

    public function testScaleZeroLinearGradientFallsBack(): void
    {
        $ops = $this->paint(
            '<linearGradient id="g" gradientTransform="scale(0)">'
            . '<stop offset="0" stop-color="yellow"/><stop offset="1" stop-color="red"/>'
            . '</linearGradient>'
            . '<rect width="50" height="100" fill="url(#g) green"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString('scn', $ops);
    }

    public function testZeroMatrixRadialGradientFallsBack(): void
    {
        $ops = $this->paint(
            '<radialGradient id="g" gradientTransform="matrix(0, 0, 0, 0, 0, 0)">'
            . '<stop offset="0" stop-color="yellow"/><stop offset="1" stop-color="red"/>'
            . '</radialGradient>'
            . '<rect width="50" height="100" fill="url(#g) green"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString('scn', $ops);
    }

    public function testScaleZeroPatternFallsBack(): void
    {
        $ops = $this->paint(
            '<pattern id="p" width="50" height="100" patternTransform="scale(0)">'
            . '<rect width="50" height="100" fill="red"/></pattern>'
            . '<rect width="50" height="100" fill="url(#p) green"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString('1 0 0 rg', $ops);
    }

    public function testZeroMatrixPatternFallsBack(): void
    {
        $ops = $this->paint(
            '<pattern id="p" width="50" height="100" patternTransform="matrix(0,0,0,0,0,0)">'
            . '<rect width="50" height="100" fill="red"/></pattern>'
            . '<rect width="50" height="100" fill="url(#p) green"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString('1 0 0 rg', $ops);
    }

    public function testAnInvertibleGradientTransformStillPaints(): void
    {
        // Guard: only a SINGULAR matrix invalidates the server. A
        // perfectly ordinary scale must keep working.
        $ops = $this->paint(
            '<linearGradient id="g" gradientTransform="scale(2)">'
            . '<stop offset="0" stop-color="yellow"/><stop offset="1" stop-color="red"/>'
            . '</linearGradient>'
            . '<rect width="50" height="100" fill="url(#g) green"/>',
        );
        self::assertStringContainsString('scn', $ops);
        self::assertStringNotContainsString(self::GREEN, $ops);
    }

    public function testAnInvertiblePatternTransformStillPaints(): void
    {
        $ops = $this->paint(
            '<pattern id="p" width="50" height="100" patternTransform="scale(2)">'
            . '<rect width="50" height="100" fill="red"/></pattern>'
            . '<rect width="50" height="100" fill="url(#p) green"/>',
        );
        self::assertStringContainsString('1 0 0 rg', $ops);
        self::assertStringNotContainsString(self::GREEN, $ops);
    }

    public function testTransformInvertibilityIsDeterminantBased(): void
    {
        // A degenerate matrix need not have any zero entry: this one
        // collapses the plane onto a line.
        self::assertFalse(Transform::parse('matrix(1, 2, 2, 4, 0, 0)')->isInvertible());
        self::assertFalse(Transform::parse('scale(0)')->isInvertible());
        self::assertFalse(Transform::parse('scale(1, 0)')->isInvertible());
        self::assertTrue(Transform::parse('scale(2)')->isInvertible());
        self::assertTrue(Transform::parse('translate(5, 5)')->isInvertible());
        self::assertTrue(Transform::parse('rotate(45)')->isInvertible());
    }
}
