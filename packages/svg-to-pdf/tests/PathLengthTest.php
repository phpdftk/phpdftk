<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §9.6 — `pathLength` declares the author's own total length for
 * an element's path. The user agent computes the real geometric length
 * and scales every distance-along-the-path quantity by
 * `geometric / author`, so `stroke-dasharray` and `stroke-dashoffset`
 * are authored in the declared units rather than user units.
 *
 * Emitted as the PDF `d` (dash) operator, so the assertions read the
 * operator list rather than rasterising.
 */
final class PathLengthTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    private function paint(string $body, bool $withCascade = false): string
    {
        $doc = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">' . $body . '</svg>',
        );
        if ($withCascade) {
            (new SvgCascadeProjector())->project($doc);
        }
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testWithoutPathLengthDashesStayInUserUnits(): void
    {
        $ops = $this->paint(
            '<path d="M10,10L110,10L110,110L10,110Z" stroke-dashoffset="1"'
            . ' stroke-dasharray="1 1" fill="none" stroke="black" stroke-width="10"/>',
        );
        self::assertStringContainsString('[ 1 1 ] 1 d', $ops);
    }

    public function testPathLengthScalesDashArrayAndOffsetOnAPath(): void
    {
        // Perimeter 400, declared 4 → factor 100.
        $ops = $this->paint(
            '<path d="M10,10L110,10L110,110L10,110Z" pathLength="4" stroke-dashoffset="1"'
            . ' stroke-dasharray="1 1" fill="none" stroke="black" stroke-width="10"/>',
        );
        self::assertStringContainsString('[ 100 100 ] 100 d', $ops);
    }

    public function testPathLengthScalesDashesOnARect(): void
    {
        // Perimeter 200, declared 4 → factor 50.
        $ops = $this->paint(
            '<rect width="50" height="50" pathLength="4" fill="blue"'
            . ' stroke-dashoffset="1" stroke-dasharray="1 1" stroke="black" stroke-width="10"/>',
        );
        self::assertStringContainsString('[ 50 50 ] 50 d', $ops);
    }

    public function testPathLengthScalesDashesOnACircle(): void
    {
        // Circumference 2π·100 = 628.3185, declared 4 → factor 157.0796;
        // 0.25 × that is 39.27, which is what the WPT reference hard-codes.
        $ops = $this->paint(
            '<circle cx="120" cy="240" r="100" pathLength="4" fill="none"'
            . ' stroke="black" stroke-width="5" stroke-dasharray="0.25"/>',
        );
        self::assertMatchesRegularExpression('/\[ 39\.269\d* \] 0 d/', $ops);
    }

    public function testPathLengthScalesDashesOnAPolygon(): void
    {
        // 200×200 closed polygon → perimeter 800, declared 4 → factor 200.
        $ops = $this->paint(
            '<polygon points="260,140, 460,140, 460,340 260,340" fill="none"'
            . ' stroke="black" stroke-width="5" stroke-dasharray="0.25" pathLength="4"/>',
        );
        self::assertStringContainsString('[ 50 ] 0 d', $ops);
    }

    public function testPathLengthScalesDashesOnALine(): void
    {
        $ops = $this->paint(
            '<line x1="0" y1="0" x2="100" y2="0" stroke="black" stroke-width="5"'
            . ' pathLength="10" stroke-dasharray="1"/>',
        );
        self::assertStringContainsString('[ 10 ] 0 d', $ops);
    }

    public function testZeroPathLengthMakesTheScaleInfiniteSoTheStrokeIsSolid(): void
    {
        // SVG 2 §9.6 — "A value of zero is valid and must be treated as
        // a scaling factor of infinity", which stretches the first dash
        // over the whole path: a solid stroke, i.e. no `d` operator.
        $ops = $this->paint(
            '<path d="M10,10L110,10L110,110L10,110Z" pathLength="0" stroke-dashoffset="1"'
            . ' stroke-dasharray="1 1" fill="none" stroke="black" stroke-width="10"/>',
        );
        self::assertStringNotContainsString(' d', "\n" . $ops . "\n");
    }

    public function testNegativePathLengthIsAnErrorAndIsIgnored(): void
    {
        // Invalid value → the attribute has no effect, so dashes stay
        // in user units.
        $ops = $this->paint(
            '<path d="M10,10L110,10L110,110L10,110Z" pathLength="-4"'
            . ' stroke-dasharray="1 1" fill="none" stroke="black" stroke-width="10"/>',
        );
        self::assertStringContainsString('[ 1 1 ] 0 d', $ops);
    }

    public function testNonNumericPathLengthIsIgnored(): void
    {
        $ops = $this->paint(
            '<path d="M10,10L110,10L110,110L10,110Z" pathLength="auto"'
            . ' stroke-dasharray="1 1" fill="none" stroke="black" stroke-width="10"/>',
        );
        self::assertStringContainsString('[ 1 1 ] 0 d', $ops);
    }

    public function testPathLengthDoesNotScaleStrokeWidth(): void
    {
        // Guard: only distance-ALONG-the-path quantities scale.
        $ops = $this->paint(
            '<rect width="50" height="50" pathLength="4" fill="none"'
            . ' stroke="black" stroke-width="10" stroke-dasharray="1"/>',
        );
        self::assertStringContainsString('10 w', $ops);
        self::assertStringNotContainsString('500 w', $ops);
    }

    public function testPathLengthWithoutDashesEmitsNoDashOperator(): void
    {
        $ops = $this->paint(
            '<rect width="50" height="50" pathLength="4" fill="none"'
            . ' stroke="black" stroke-width="10"/>',
        );
        self::assertStringNotContainsString(' d', "\n" . $ops . "\n");
    }

    public function testCssPathLengthPropertyIsHonoured(): void
    {
        // Perimeter 800, declared 10 → factor 80; 0.25 × 80 = 20.
        $ops = $this->paint(
            '<style>rect { path-length: 10; }</style>'
            . '<rect x="20" y="20" width="200" height="200" fill="none"'
            . ' stroke="black" stroke-width="5" stroke-dasharray="0.25"/>',
            withCascade: true,
        );
        self::assertStringContainsString('[ 20 ] 0 d', $ops);
    }

    public function testCssPathLengthOverridesThePresentationAttribute(): void
    {
        $ops = $this->paint(
            '<style>rect { path-length: 10; }</style>'
            . '<rect x="20" y="20" width="200" height="200" pathLength="100" fill="none"'
            . ' stroke="black" stroke-width="5" stroke-dasharray="0.25"/>',
            withCascade: true,
        );
        self::assertStringContainsString('[ 20 ] 0 d', $ops);
        self::assertStringNotContainsString('[ 2 ] 0 d', $ops);
    }
}
