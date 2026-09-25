<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §8.9 — `display: none` removes an element AND its descendants
 * from the rendering tree. Unlike `visibility`, a descendant cannot
 * opt back in.
 *
 * The painter never read `display` at all, so a hidden group painted
 * exactly like a visible one. WPT fixtures routinely park an
 * alternative rendering inside the test file behind `display: none`,
 * so this was drawing content the author had explicitly switched off.
 */
final class DisplayNoneTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testDisplayNoneOnTheElementItself(): void
    {
        self::assertSame('', $this->paint('<rect width="10" height="10" display="none"/>'));
    }

    public function testDisplayNoneHidesDescendants(): void
    {
        self::assertSame(
            '',
            $this->paint('<g display="none"><rect width="10" height="10"/></g>'),
        );
    }

    public function testADescendantCannotOptBackIn(): void
    {
        // The `display` vs `visibility` distinction: `display` is not
        // inherited, but removal from the rendering tree IS structural,
        // so `display: inline` on a child of a hidden group changes
        // nothing.
        self::assertSame(
            '',
            $this->paint(
                '<g display="none"><rect width="10" height="10" display="inline"/></g>',
            ),
        );
    }

    public function testAStylesheetRuleReachesTheProperty(): void
    {
        self::assertSame(
            '',
            $this->paint(
                '<style>#hidden { display: none }</style>'
                . '<g id="hidden"><rect width="10" height="10"/></g>',
            ),
        );
    }

    public function testOtherDisplayValuesStillPaint(): void
    {
        // Guard: SVG treats every non-`none` display value as "render
        // it"; only `none` removes the element.
        self::assertStringContainsString(
            '0 0 10 10 re',
            $this->paint('<rect width="10" height="10" display="inline"/>'),
        );
        self::assertStringContainsString(
            '0 0 10 10 re',
            $this->paint('<rect width="10" height="10" display="block"/>'),
        );
    }

    public function testNoDisplayDeclarationStillPaints(): void
    {
        self::assertStringContainsString(
            '0 0 10 10 re',
            $this->paint('<rect width="10" height="10"/>'),
        );
    }

    public function testDisplayNoneOnAReferencedSymbolSuppressesTheInstance(): void
    {
        // WPT struct/reftests/use-symbol-display-none. The `<symbol>`
        // branch of the `<use>` painter bypasses `paintElement()`, so
        // the check has to exist there too.
        self::assertStringNotContainsString(
            '1 0 0 rg',
            $this->paint(
                '<defs><symbol id="a" display="none" width="100" height="100">'
                . '<rect width="100" height="100" fill="red"/></symbol></defs>'
                . '<use href="#a"/>',
            ),
        );
    }

    public function testAVisibleSymbolInstanceStillPaints(): void
    {
        // Guard for the line above.
        self::assertStringContainsString(
            '1 0 0 rg',
            $this->paint(
                '<defs><symbol id="a" width="100" height="100">'
                . '<rect width="100" height="100" fill="red"/></symbol></defs>'
                . '<use href="#a"/>',
            ),
        );
    }

    public function testAUseReferentParkedInDefsIsNotHidden(): void
    {
        // Guard: `<defs>` is "never rendered DIRECTLY", which is not
        // the same as `display: none`. A `<use>` must still pull its
        // content through.
        self::assertStringContainsString(
            '0 0 10 10 re',
            $this->paint(
                '<defs><rect id="r" width="10" height="10"/></defs><use href="#r"/>',
            ),
        );
    }
}
