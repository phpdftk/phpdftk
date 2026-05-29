<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3M: the `transform` attribute lowers to PDF `cm`, wrapped in
 * `q`/`Q` so siblings aren't affected. SVG 2 §8.4 allows the
 * attribute on any element that establishes a new coordinate system,
 * so the painter checks it uniformly rather than per shape.
 */
final class TransformTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    private function paint(string $svg): string
    {
        $doc = $this->svgParser->parse($svg);
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testGroupWithTransformWrapsChildren(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<g transform="translate(10, 20)">'
            . '<rect width="5" height="5"/>'
            . '</g>'
            . '</svg>',
        );
        // q saves state, cm applies the translation, then the child
        // shape's operators, then Q restores.
        $expectedOrder = [
            'q',
            '1 0 0 1 10 20 cm',
            '0 0 5 5 re',
            'Q',
        ];
        $lastIndex = -1;
        foreach ($expectedOrder as $needle) {
            $found = strpos($ops, $needle, $lastIndex + 1);
            self::assertNotFalse($found, "Missing $needle in: $ops");
            $lastIndex = $found;
        }
    }

    /**
     * Counts how many lines in `$ops` are exactly the operator `$op`
     * (no leading args). Useful because q/Q/h sit alone on a line and
     * `substr_count` against `"\n$op\n"` misses occurrences at the
     * start or end of the joined output.
     */
    private static function countLines(string $ops, string $op): int
    {
        return count(array_filter(explode("\n", $ops), static fn(string $l): bool => $l === $op));
    }

    public function testShapeWithTransformWrapsItselfNotJustGroups(): void
    {
        // SVG 2 §8.4 — the transform attribute on a rectangle works the
        // same as on a `<g>`. Painter shouldn't grow per-element branches
        // for the wrapping.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="5" height="5" transform="scale(2)"/>'
            . '</svg>',
        );
        self::assertSame(1, self::countLines($ops, 'q'));
        self::assertStringContainsString('2 0 0 2 0 0 cm', $ops);
        self::assertStringContainsString('0 0 5 5 re', $ops);
        self::assertSame(1, self::countLines($ops, 'Q'));
    }

    public function testElementWithoutTransformIsNotWrapped(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="5" height="5"/></svg>',
        );
        self::assertSame(0, self::countLines($ops, 'q'));
        self::assertStringNotContainsString(' cm', $ops);
        self::assertSame(0, self::countLines($ops, 'Q'));
    }

    public function testGroupWithoutTransformStillRecursesIntoChildren(): void
    {
        // No q/cm/Q wrap when the `<g>` carries no transform, but the
        // child still paints — confirms the dispatch default arm for
        // unknown / container elements continues to walk in.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<g><rect width="5" height="5"/></g>'
            . '</svg>',
        );
        self::assertSame(0, self::countLines($ops, 'q'));
        self::assertStringContainsString('0 0 5 5 re', $ops);
    }

    public function testNestedGroupTransformsCompose(): void
    {
        // Two nested wraps: outer translate, inner scale. Painter emits
        // both `q`s before the inner shape and both `Q`s after.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<g transform="translate(10, 20)">'
            . '<g transform="scale(2)">'
            . '<rect width="1" height="1"/>'
            . '</g>'
            . '</g>'
            . '</svg>',
        );
        self::assertSame(2, self::countLines($ops, 'q'));
        self::assertSame(2, self::countLines($ops, 'Q'));
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
        self::assertStringContainsString('2 0 0 2 0 0 cm', $ops);
    }

    public function testSvgViewBoxAppliesOriginShiftOnly(): void
    {
        // `viewBox="-50 -50 100 100"` translates the local coordinate
        // system by (+50, +50) so authored coords stay aligned with
        // the viewBox origin. Full viewBox-to-viewport mapping (scale
        // + preserveAspectRatio) lands in 3R when the caller can
        // supply a target rectangle.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-50 -50 100 100">'
            . '<rect width="10" height="10"/>'
            . '</svg>',
        );
        self::assertStringContainsString('1 0 0 1 50 50 cm', $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testSvgWithZeroOriginViewBoxDoesNotEmitCm(): void
    {
        // `0 0 W H` is a no-op translation — skip the wrap so the
        // content stream stays clean.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<rect width="10" height="10"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString(' cm', $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }
}
