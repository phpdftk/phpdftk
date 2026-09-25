<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests\Shape;

use Phpdftk\Css\Shape\BasicShapePath;
use Phpdftk\Css\Value\BasicShape;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Shapes 1 §3.1 — `inset()` takes an optional
 * `round <border-radius>`, which rounds the corners of the inset
 * rectangle. `InsetShape` has always PARSED the radius into
 * `$borderRadius`; the path builder ignored it and emitted square
 * corners.
 */
final class InsetRoundTest extends TestCase
{
    /** @return array{fillRule: string, commands: list<list<string|float>>} */
    private function build(string $css, float $w = 200.0, float $h = 200.0): array
    {
        $shape = (new ValueParser())->parseFromString($css);
        self::assertInstanceOf(BasicShape::class, $shape);
        $path = BasicShapePath::build($shape, 0.0, 0.0, $w, $h);
        self::assertIsArray($path);
        return $path;
    }

    /** @param array{fillRule: string, commands: list<list<string|float>>} $path */
    private function verbs(array $path): string
    {
        return implode('', array_map(
            static fn(array $c): string => (string) $c[0],
            $path['commands'],
        ));
    }

    public function testASquareInsetStaysFiveStraightCommands(): void
    {
        // Guard: no `round`, no curves.
        self::assertSame('MLLLZ', $this->verbs($this->build('inset(10%)')));
    }

    public function testRoundEmitsFourCornerCurves(): void
    {
        self::assertSame(4, substr_count($this->verbs($this->build('inset(10% round 10%)')), 'C'));
    }

    public function testRadiusPercentagesResolveAgainstTheReferenceBox(): void
    {
        // WPT css-masking/clip-path-shape-inset-001 pins this: on a
        // 200x200 box, `inset(10% round 10%)` matches
        // `<rect x="20" y="20" width="160" height="160" rx="20" ry="20">`.
        // The radius is 10 % of 200, NOT of the 160-wide inset rect.
        $path = $this->build('inset(10% round 10%)');
        // The path starts at the end of the top-left corner arc:
        // x = left inset + radius = 20 + 20, y = top inset = 20.
        self::assertEqualsWithDelta(40.0, (float) $path['commands'][0][1], 1.0e-9);
        self::assertEqualsWithDelta(20.0, (float) $path['commands'][0][2], 1.0e-9);
    }

    public function testAbsoluteRadiusMatchesTheEquivalentPercentage(): void
    {
        // css-masking/clip-path-shape-inset-002 shares -001's reference.
        self::assertEquals(
            $this->build('inset(10% round 10%)'),
            $this->build('inset(20px round 20px)'),
        );
    }

    public function testOversizedRadiiAreScaledDownUniformly(): void
    {
        // CSS Backgrounds 3 §5.5 — when adjacent radii overrun a side,
        // every radius scales by the same factor so the corners just
        // touch. Without it the corner curves cross over and the clip
        // region turns inside out.
        $path = $this->build('inset(0 round 200px)', 100.0, 100.0);
        // Radii clamp to half the side: the top edge collapses to a
        // point at the centre.
        self::assertEqualsWithDelta(50.0, (float) $path['commands'][0][1], 1.0e-9);
    }

    public function testAZeroRadiusStaysSquare(): void
    {
        // Guard: `round 0` must not emit degenerate zero-length curves.
        self::assertSame('MLLLZ', $this->verbs($this->build('inset(10% round 0)')));
    }

    public function testTheTwoAxisSlashFormGivesEachAxisItsOwnRadius(): void
    {
        // CSS Backgrounds 3 §5.5 — `round A / B` makes A the
        // horizontal radii and B the vertical ones. A flat value list
        // cannot express that, so the parser has to keep the halves
        // apart; concatenating them made
        // css-masking/clip-path-inset-round-rendering's REFERENCE round
        // its corners by the horizontal radius on both axes.
        $split = $this->build('inset(0 round 40px / 10px)', 100.0, 100.0);
        $uniform = $this->build('inset(0 round 40px)', 100.0, 100.0);
        self::assertNotEquals($uniform, $split);
        // First command is the end of the top-left arc: x = rx = 40.
        self::assertEqualsWithDelta(40.0, (float) $split['commands'][0][1], 1.0e-9);
        // Last straight run before closing reaches y = ry = 10.
        $left = $split['commands'][7];
        self::assertSame('L', $left[0]);
        self::assertEqualsWithDelta(10.0, (float) $left[2], 1.0e-9);
    }

    public function testPerCornerRadiiExpandLikeBorderRadius(): void
    {
        // One value applies to all four corners; two alternate
        // (top-left/bottom-right, then top-right/bottom-left).
        $uniform = $this->build('inset(0 round 10px)');
        self::assertEquals($uniform, $this->build('inset(0 round 10px 10px)'));
        self::assertNotEquals($uniform, $this->build('inset(0 round 10px 20px)'));
    }
}
