<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3O — gradient fills. `fill="url(#id)"` resolves the referenced
 * `<linearGradient>` / `<radialGradient>`, registers a PDF
 * `ShadingType2` / `ShadingType3` via `PdfDoc`, attaches the pattern
 * resource to the page, and emits `Pattern` colour-space + pattern-name
 * fill ops on the content stream. Requires both `Page` and `PdfWriter`
 * for registration to work; without either the painter falls back to no
 * fill per SVG 2's "invalid → no paint" rule.
 */
final class GradientTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    /**
     * Paint with the full writer + page reference set so gradients
     * actually register.
     *
     * @return array{ops: string, bytes: string}
     */
    private function paintWithWriter(string $svg): array
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page, $writer);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    /**
     * Helper that runs the painter with stream compression disabled so
     * the gradient's Pattern dict — including its `/Matrix` and the
     * Shading's `/Coords` — is visible in the byte stream for
     * grepping.
     *
     * @return array{ops: string, bytes: string}
     */
    private function paintUncompressed(string $svg): array
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page, $writer);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    public function testLinearGradientFillEmitsPatternColorSpaceAndShading(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><linearGradient id="g" gradientUnits="userSpaceOnUse" '
            . 'x1="0" y1="0" x2="100" y2="0">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></defs>'
            . '<rect width="100" height="50" fill="url(#g)"/>'
            . '</svg>',
        );
        // Pattern colour space + tinted-pattern fill name:
        self::assertStringContainsString('/Pattern cs', $result['ops']);
        self::assertMatchesRegularExpression('!/P\d+ scn!', $result['ops']);
        // PDF body should carry a ShadingType 2 dict (axial) for the
        // registered gradient.
        self::assertStringContainsString('/ShadingType 2', $result['bytes']);
    }

    public function testRadialGradientRegistersShadingType3(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><radialGradient id="r" gradientUnits="userSpaceOnUse" '
            . 'cx="50" cy="50" r="40">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</radialGradient></defs>'
            . '<rect width="100" height="100" fill="url(#r)"/>'
            . '</svg>',
        );
        self::assertStringContainsString('/ShadingType 3', $result['bytes']);
    }

    public function testObjectBoundingBoxModeMapsStopsToElementBbox(): void
    {
        // Default `gradientUnits` per SVG 2 §13.6.5 is
        // objectBoundingBox. The gradient's `(0, 0)`-`(1, 0)`
        // coordinate range should reify to the rect's user-space
        // coordinates `(10, 20)` → `(60, 20)` (width 50).
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><linearGradient id="g">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></defs>'
            . '<rect x="10" y="20" width="50" height="30" fill="url(#g)"/>'
            . '</svg>',
        );
        // ShadingType2 Coords array should reflect the bbox-reified
        // start / end points.
        self::assertMatchesRegularExpression('!/Coords \[ 10 20 60 20 \]!', $result['bytes']);
    }

    public function testMissingGradientFallsBackToNoFill(): void
    {
        // `url(#nope)` doesn't resolve to any gradient; per spec the
        // paint is invalid and the fill is skipped. Painter emits `n`
        // (endPath) because the path was constructed but nothing wants
        // to fill it.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="url(#nope)"/>'
            . '</svg>',
        );
        self::assertStringContainsString("\nn", $result['ops']);
        self::assertStringNotContainsString('/Pattern', $result['ops']);
    }

    public function testGradientWithoutWriterReferenceFallsBackToNoFill(): void
    {
        // Same gradient SVG but the caller didn't pass a writer →
        // gradient painter never instantiated, fill becomes a no-op.
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><linearGradient id="g">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></defs>'
            . '<rect width="10" height="10" fill="url(#g)"/>'
            . '</svg>',
        );
        $this->translator->paint($doc, $stream, $page); // no writer arg
        $ops = implode("\n", $stream->getOperators());
        self::assertStringContainsString("\nn", $ops);
        self::assertStringNotContainsString('/Pattern', $ops);
    }

    public function testThreeStopGradientUsesStitchingFunction(): void
    {
        // Three+ stops triggers `PdfDoc::addLinearGradientStops`'s
        // FunctionType3 (stitching) branch — visible in the PDF body
        // as `/FunctionType 3` and `/Bounds`.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><linearGradient id="g" gradientUnits="userSpaceOnUse" '
            . 'x1="0" y1="0" x2="100" y2="0">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="0.5" stop-color="white"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></defs>'
            . '<rect width="100" height="50" fill="url(#g)"/>'
            . '</svg>',
        );
        self::assertStringContainsString('/FunctionType 3', $result['bytes']);
        self::assertStringContainsString('/Bounds', $result['bytes']);
    }

    public function testGradientHrefInheritanceWorksAcrossDefs(): void
    {
        // `<linearGradient id="b" href="#a"/>` inherits its stops from
        // `#a` per SVG 2 §13.4. The painter should still produce a
        // valid ShadingType 2 referencing the inherited stops.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<linearGradient id="a">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient>'
            . '<linearGradient id="b" gradientUnits="userSpaceOnUse" '
            . 'x1="0" y1="0" x2="10" y2="0" href="#a"/>'
            . '</defs>'
            . '<rect width="10" height="10" fill="url(#b)"/>'
            . '</svg>',
        );
        self::assertStringContainsString('/ShadingType 2', $result['bytes']);
    }

    public function testGradientTransformIsBakedIntoPatternMatrix(): void
    {
        // `gradientTransform` maps gradient (pattern) space to user
        // space — exactly what the PDF pattern `/Matrix` entry does.
        // The painter sets it from the 3C-derived 3×2 affine matrix.
        $result = $this->paintUncompressed(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><linearGradient id="g" gradientUnits="userSpaceOnUse" '
            . 'x1="0" y1="0" x2="100" y2="0" '
            . 'gradientTransform="rotate(45)">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></defs>'
            . '<rect width="100" height="50" fill="url(#g)"/>'
            . '</svg>',
        );
        // rotate(45) → [cos, sin, -sin, cos, 0, 0] ≈ [0.707..., 0.707..., -0.707..., 0.707..., 0, 0].
        self::assertMatchesRegularExpression(
            '!/Matrix \[ 0\.707\d+ 0\.707\d+ -0\.707\d+ 0\.707\d+ 0 0 \]!',
            $result['bytes'],
        );
    }

    public function testGradientTransformAbsentEmitsNoMatrixEntry(): void
    {
        // No `gradientTransform` → no `/Matrix` in the pattern dict
        // (PDF default = identity).
        $result = $this->paintUncompressed(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><linearGradient id="g" gradientUnits="userSpaceOnUse" '
            . 'x1="0" y1="0" x2="100" y2="0">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</linearGradient></defs>'
            . '<rect width="100" height="50" fill="url(#g)"/>'
            . '</svg>',
        );
        // ShadingPattern only emits `/Matrix` when set; absent
        // gradientTransform leaves the dict without the key.
        self::assertStringNotContainsString('/Matrix', $result['bytes']);
    }

    public function testRadialFocalPointReachesInnerCircle(): void
    {
        // `fx` / `fy` / `fr` lower into PDF ShadingType 3's `/Coords`
        // array as the first three numbers (inner circle).
        $result = $this->paintUncompressed(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><radialGradient id="r" gradientUnits="userSpaceOnUse" '
            . 'cx="50" cy="50" r="40" fx="30" fy="35" fr="5">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</radialGradient></defs>'
            . '<rect width="100" height="100" fill="url(#r)"/>'
            . '</svg>',
        );
        // ShadingType 3 Coords: [fx fy fr cx cy r].
        self::assertStringContainsString('/Coords [ 30 35 5 50 50 40 ]', $result['bytes']);
    }

    public function testRadialFocalPointDefaultsToCentre(): void
    {
        // Per SVG 2 §13.7.5, absent fx/fy default to cx/cy and fr to 0.
        $result = $this->paintUncompressed(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><radialGradient id="r" gradientUnits="userSpaceOnUse" '
            . 'cx="60" cy="40" r="30">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/>'
            . '</radialGradient></defs>'
            . '<rect width="100" height="100" fill="url(#r)"/>'
            . '</svg>',
        );
        self::assertStringContainsString('/Coords [ 60 40 0 60 40 30 ]', $result['bytes']);
    }
}
