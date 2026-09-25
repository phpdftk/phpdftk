<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.3 — a `<pattern>` may carry a `viewBox`, which establishes
 * a coordinate system for its content mapped into the tile rectangle,
 * exactly as it does for a nested `<svg>`. `Pattern::viewBox()` existed
 * in the model and nothing read it, so a pattern whose content was
 * authored in viewBox coordinates painted at raw coordinates — usually
 * far off the tile and therefore invisible.
 */
final class PatternViewBoxTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testAViewBoxOriginShiftsThePatternContent(): void
    {
        // viewBox min-x of 50 mapped into a 50-wide tile is
        // translate(-50) at scale 1.
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50"'
            . ' viewBox="50 0 50 50"><rect x="50" width="50" height="50" fill="black"/></pattern>'
            . '<rect width="50" height="50" fill="url(#p)"/>',
        );
        self::assertStringContainsString('1 0 0 1 -50 0 cm', $ops);
    }

    public function testAViewBoxScalesContentIntoTheTile(): void
    {
        // A 10x10 viewBox in a 50x50 tile scales by 5.
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50"'
            . ' viewBox="0 0 10 10"><rect width="10" height="10" fill="black"/></pattern>'
            . '<rect width="50" height="50" fill="url(#p)"/>',
        );
        self::assertStringContainsString('5 0 0 5 0 0 cm', $ops);
    }

    public function testPreserveAspectRatioAppliesToTheMapping(): void
    {
        // A square viewBox in a 100x50 tile uniformly scales by 0.5x
        // (meet) and centres horizontally under the default xMidYMid.
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="100" height="50"'
            . ' viewBox="0 0 100 100"><rect width="100" height="100" fill="black"/></pattern>'
            . '<rect width="100" height="50" fill="url(#p)"/>',
        );
        self::assertStringContainsString('0.5 0 0 0.5 25 0 cm', $ops);
    }

    public function testNoViewBoxLeavesTheContentAlone(): void
    {
        // Guard: the ordinary pattern path must not gain a transform.
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect width="25" height="25" fill="black"/></pattern>'
            . '<rect width="50" height="50" fill="url(#p)"/>',
        );
        self::assertSame(1, substr_count($ops, ' cm'));
    }

    public function testADegenerateViewBoxIsIgnored(): void
    {
        // Guard: a zero-width viewBox would divide by zero in the
        // mapping; the spec says such a value disables rendering of
        // the element, and ignoring it here at least keeps the tile
        // sane rather than emitting a NaN matrix.
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50"'
            . ' viewBox="0 0 0 50"><rect width="25" height="25" fill="black"/></pattern>'
            . '<rect width="50" height="50" fill="url(#p)"/>',
        );
        self::assertStringNotContainsString('NAN', strtoupper($ops));
        self::assertSame(1, substr_count($ops, ' cm'));
    }
}
