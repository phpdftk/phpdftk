<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.3 — a `<pattern>` may reference another through `href`
 * (legacy `xlink:href`). Attributes it does not specify itself are
 * inherited from the referenced pattern, and a pattern with no element
 * children paints the referenced pattern's children.
 */
final class PatternHrefTemplateTest extends TestCase
{
    private function paint(string $svg): string
    {
        $doc = (new SvgParser())->parse($svg);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    private static function svg(string $defs): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<defs>' . $defs . '</defs>'
            . '<rect width="100" height="100" fill="url(#copy)"/>'
            . '</svg>';
    }

    public function testChildlessPatternWithoutHrefIgnoresOtherPatternsInTheDocument(): void
    {
        // Guard the negative case the href walk must not accidentally
        // "fix": a childless pattern with no template has no content,
        // and must not borrow a sibling pattern's children.
        $ops = $this->paint(self::svg(
            '<pattern id="other" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . '<pattern id="copy" patternUnits="userSpaceOnUse" width="50" height="50"></pattern>',
        ));
        self::assertStringNotContainsString('5 5 20 20 re', $ops);
    }

    public function testHrefToAMissingIdPaintsNothing(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="decoy" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . '<pattern id="copy" href="#nope" patternUnits="userSpaceOnUse" width="50" height="50"></pattern>',
        ));
        self::assertStringNotContainsString('5 5 20 20 re', $ops);
    }

    public function testHrefToANonPatternElementPaintsNothing(): void
    {
        $ops = $this->paint(self::svg(
            '<rect id="base" x="5" y="5" width="20" height="20" fill="green"/>'
            . '<pattern id="copy" href="#base" patternUnits="userSpaceOnUse" width="50" height="50"></pattern>',
        ));
        self::assertStringNotContainsString('5 5 20 20 re', $ops);
    }

    public function testSelfReferencingHrefTerminatesAndPaintsNothing(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="decoy" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . '<pattern id="copy" href="#copy" patternUnits="userSpaceOnUse" width="50" height="50"></pattern>',
        ));
        self::assertStringNotContainsString('5 5 20 20 re', $ops);
    }

    /**
     * A reference cycle is an error per SVG 2, but the renderer must
     * terminate rather than recurse — and content reached before the
     * cycle closes still paints.
     */
    public function testMutuallyReferencingPatternsTerminate(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="copy" href="#other" patternUnits="userSpaceOnUse" width="50" height="50"></pattern>'
            . '<pattern id="other" href="#copy" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>',
        ));
        self::assertStringContainsString('5 5 20 20 re', $ops);
    }

    public function testChildlessPatternPaintsTheTemplatesChildren(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="base" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . '<pattern id="copy" href="#base"></pattern>',
        ));
        self::assertStringContainsString('5 5 20 20 re', $ops);
    }

    /**
     * Whitespace between `<pattern>` tags parses as a text node; it must
     * not count as content or the copy paints an empty tile.
     */
    public function testWhitespaceOnlyPatternStillInheritsTemplateChildren(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="base" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . "<pattern id=\"copy\" href=\"#base\">\n    </pattern>",
        ));
        self::assertStringContainsString('5 5 20 20 re', $ops);
    }

    /** SVG 2 §5.10 — `href` wins over the legacy `xlink:href`. */
    public function testHrefTakesPrecedenceOverXlinkHref(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="wanted" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . '<pattern id="legacy" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="30" y="30" width="7" height="7" fill="red"/>'
            . '</pattern>'
            . '<pattern id="copy" href="#wanted" xlink:href="#legacy"></pattern>',
        ));
        self::assertStringContainsString('5 5 20 20 re', $ops);
        self::assertStringNotContainsString('30 30 7 7 re', $ops);
    }

    public function testXlinkHrefStillWorksOnItsOwn(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="base" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . '<pattern id="copy" xlink:href="#base"></pattern>',
        ));
        self::assertStringContainsString('5 5 20 20 re', $ops);
    }

    /**
     * The referencing pattern's own attributes beat the template's —
     * inheritance only fills the gaps.
     */
    public function testOwnAttributesOverrideTheTemplates(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="base" patternUnits="userSpaceOnUse" x="0" y="0" width="50" height="50">'
            . '<rect x="0" y="0" width="10" height="10" fill="green"/>'
            . '</pattern>'
            . '<pattern id="copy" href="#base" width="25"></pattern>',
        ));
        // A 25-wide tile across the 100-wide shape tiles four times per
        // row rather than the template's two.
        $tiles = substr_count($ops, '0 0 10 10 re');
        self::assertSame(8, $tiles, 'own width=25 → 4 columns x 2 rows of the template tile');
    }

    public function testTemplateChainResolvesThroughTwoHops(): void
    {
        $ops = $this->paint(self::svg(
            '<pattern id="base" patternUnits="userSpaceOnUse" width="50" height="50">'
            . '<rect x="5" y="5" width="20" height="20" fill="green"/>'
            . '</pattern>'
            . '<pattern id="mid" href="#base"></pattern>'
            . '<pattern id="copy" href="#mid"></pattern>',
        ));
        self::assertStringContainsString('5 5 20 20 re', $ops);
    }
}
