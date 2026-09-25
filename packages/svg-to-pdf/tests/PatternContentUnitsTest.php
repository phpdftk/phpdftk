<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.3 — `patternContentUnits="objectBoundingBox"` puts a
 * pattern's CONTENT in bounding-box units: one unit is the referencing
 * shape's full extent, so `<rect width="1" height="1"/>` covers it.
 *
 * `Pattern::patternContentUnits()` existed and nothing read it, so
 * content authored in bounding-box units painted at raw user
 * coordinates — a one-pixel speck instead of a full tile.
 */
final class PatternContentUnitsTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    /** The default is `userSpaceOnUse`, which emits no content matrix. */
    public function testUserSpaceOnUseIsTheDefaultAndScalesNothing(): void
    {
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect width="50" height="50" fill="#00ff00"/></pattern>'
            . '<rect x="10" y="20" width="100" height="40" fill="url(#p)"/>',
        );
        self::assertStringNotContainsString('100 0 0 40 10 20 cm', $ops);
    }

    public function testObjectBoundingBoxScalesContentByTheShapesBox(): void
    {
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50"'
            . ' patternContentUnits="objectBoundingBox">'
            . '<rect width="1" height="1" fill="#00ff00"/></pattern>'
            . '<rect x="10" y="20" width="100" height="40" fill="url(#p)"/>',
        );
        self::assertStringContainsString('100 0 0 40 10 20 cm', $ops);
    }

    /**
     * The attribute is IGNORED when a `viewBox` is present — the
     * viewBox establishes the content coordinate system instead.
     */
    public function testAViewBoxOverridesTheContentUnits(): void
    {
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50"'
            . ' patternContentUnits="objectBoundingBox" viewBox="0 0 10 10">'
            . '<rect width="10" height="10" fill="#00ff00"/></pattern>'
            . '<rect x="10" y="20" width="100" height="40" fill="url(#p)"/>',
        );
        // The viewBox mapping (10 -> 50 in both axes) wins.
        self::assertStringContainsString('5 0 0 5 0 0 cm', $ops);
        self::assertStringNotContainsString('100 0 0 40 10 20 cm', $ops);
    }

    /**
     * A percentage-sized shape gets a percentage-resolved box, so the
     * tile covers the shape rather than a hundred-unit corner of it.
     */
    public function testAPercentageSizedShapeResolvesItsBox(): void
    {
        $ops = $this->paint(
            '<pattern id="p" width="1" height="1" patternContentUnits="objectBoundingBox">'
            . '<rect width="1" height="1" fill="#00ff00"/></pattern>'
            . '<rect width="100%" height="100%" fill="url(#p)"/>',
        );
        self::assertStringContainsString('200 0 0 100 0 0 cm', $ops);
    }

    /** A degenerate box has no units to scale by, so nothing is emitted. */
    public function testAZeroSizedBoxEmitsNoContentMatrix(): void
    {
        $ops = $this->paint(
            '<pattern id="p" patternUnits="userSpaceOnUse" width="50" height="50"'
            . ' patternContentUnits="objectBoundingBox">'
            . '<rect width="1" height="1" fill="#00ff00"/></pattern>'
            . '<rect width="0" height="40" fill="url(#p)"/>',
        );
        self::assertStringNotContainsString('cm', $ops);
    }
}
