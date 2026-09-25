<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.3 — `patternTransform` applies an extra transform to the
 * pattern tile's coordinate system, moving the whole tiling lattice
 * while the shape it fills stays put.
 *
 * `<pattern>` had no accessor for the attribute at all, so the tiles
 * painted unshifted.
 */
final class PatternTransformTest extends TestCase
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

    public function testPatternTransformConcatenatesBeforeTheTiles(): void
    {
        $ops = $this->paint(
            '<defs><pattern id="p" patternUnits="userSpaceOnUse" width="20" height="20">'
            . '<rect width="10" height="10" fill="black"/></pattern></defs>'
            . '<rect width="40" height="40" fill="url(#p)"/>',
        );
        $shifted = $this->paint(
            '<defs><pattern id="p" patternTransform="translate(5 5)"'
            . ' patternUnits="userSpaceOnUse" width="20" height="20">'
            . '<rect width="10" height="10" fill="black"/></pattern></defs>'
            . '<rect width="40" height="40" fill="url(#p)"/>',
        );
        self::assertStringNotContainsString('1 0 0 1 5 5 cm', $ops);
        self::assertStringContainsString('1 0 0 1 5 5 cm', $shifted);
    }

    public function testTheTilingRangeCoversTheShapeAfterTheTransform(): void
    {
        // A positive translate moves the lattice forward, so the loop
        // has to start one tile EARLIER to keep covering the shape's
        // near edge. Without inverse-mapping the bounding box the
        // top-left corner came out unpainted.
        $ops = $this->paint(
            '<defs><pattern id="p" patternTransform="translate(5 5)"'
            . ' patternUnits="userSpaceOnUse" width="20" height="20">'
            . '<rect width="10" height="10" fill="black"/></pattern></defs>'
            . '<rect width="40" height="40" fill="url(#p)"/>',
        );
        self::assertStringContainsString('1 0 0 1 -20 -20 cm', $ops);
    }

    public function testNoPatternTransformEmitsNoExtraMatrix(): void
    {
        // Guard: the ordinary pattern path must not grow a wrap.
        $ops = $this->paint(
            '<defs><pattern id="p" patternUnits="userSpaceOnUse" width="20" height="20">'
            . '<rect width="10" height="10" fill="black"/></pattern></defs>'
            . '<rect width="20" height="20" fill="url(#p)"/>',
        );
        self::assertSame(1, substr_count($ops, ' cm'));
    }

    public function testAScalingPatternTransformIsHonoured(): void
    {
        $ops = $this->paint(
            '<defs><pattern id="p" patternTransform="scale(2)"'
            . ' patternUnits="userSpaceOnUse" width="20" height="20">'
            . '<rect width="10" height="10" fill="black"/></pattern></defs>'
            . '<rect width="40" height="40" fill="url(#p)"/>',
        );
        self::assertStringContainsString('2 0 0 2 0 0 cm', $ops);
    }
}
