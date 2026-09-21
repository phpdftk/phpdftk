<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §8.6 — `<image>` referencing an SVG resource.
 *
 * The element establishes a viewport for the referenced document and
 * renders that document's own tree into it. There is no raster to
 * embed, so this never reaches `PdfWriter::addImage` (which rejects
 * SVG bytes outright) — before this path existed, every SVG-valued
 * `<image href>` painted nothing at all.
 */
final class EmbeddedSvgImageTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    private function paintOps(string $svg): string
    {
        $doc = $this->svgParser->parse($svg);
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    /** Wrap SVG markup as a `data:image/svg+xml,` URI (percent-encoded). */
    private static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml,' . rawurlencode($svg);
    }

    private static function host(string $imageTag): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">'
            . $imageTag . '</svg>';
    }

    // ---------------------------------------------------------------
    // Negative cases: the "no image available" outcomes must stay
    // silent rather than emitting stray operators.
    // ---------------------------------------------------------------

    public function testAnUnparseableSvgPayloadPaintsNothing(): void
    {
        $uri = self::dataUri('<svg xmlns="http://www.w3.org/2000/svg"><rect fill="green"');
        $ops = $this->paintOps(self::host(
            sprintf('<image width="100" height="100" href="%s"/>', $uri),
        ));
        self::assertSame('', $ops);
    }

    public function testAZeroWidthImageViewportPaintsNothing(): void
    {
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image width="0" height="100" href="%s"/>', $uri),
        ));
        self::assertSame('', $ops);
    }

    public function testAZeroSizedNearestViewportLeavesADimensionlessImageUnpainted(): void
    {
        // `auto` width/height fall back to the nearest SVG viewport as
        // their default object size — when that viewport is itself
        // degenerate there is nothing to render into.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="50" height="50" fill="green"/></svg>',
        );
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . sprintf('<image href="%s"/>', $uri)
            . '</svg>',
        );
        self::assertSame('', $ops);
    }

    public function testAnEmbeddedDocumentCannotResolveIdsFromTheHostDocument(): void
    {
        // The referenced resource is a SEPARATE document — its
        // `url(#…)` references must not reach into the host's id space.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">'
            . '<rect width="50" height="50" fill="url(#hostClip)"/></svg>',
        );
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">'
            . '<linearGradient id="hostClip"><stop offset="0" stop-color="red"/></linearGradient>'
            . sprintf('<image width="100" height="100" href="%s"/>', $uri)
            . '</svg>',
        );
        // Unresolvable paint → no fill emitted, per SVG 2's
        // "invalid reference → no paint".
        self::assertStringNotContainsString(' rg', $ops);
        self::assertStringNotContainsString(' scn', $ops);
    }

    public function testASelfReferencingImageTerminates(): void
    {
        $dir = sys_get_temp_dir() . '/phpdftk-embed-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $path = $dir . '/cycle.svg';
        file_put_contents(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">'
            . '<image width="50" height="50" href="' . $path . '"/></svg>',
        );
        try {
            $ops = $this->paintOps(self::host(
                sprintf('<image width="100" height="100" href="%s"/>', $path),
            ));
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
        // Termination is the assertion: a cycle must not recurse
        // forever, and the nesting it DID expand is bounded.
        self::assertLessThan(10, substr_count($ops, ' re'));
    }

    public function testAnInlineHexColourIsNotMistakenForAUrlFragment(): void
    {
        // `#` is the RFC 3986 fragment delimiter, so a payload that
        // inlines an unencoded hex colour would be truncated mid-
        // document by a naive split. The fragment can only be the
        // whole remainder after the document's final `>`.
        $uri = 'data:image/svg+xml,' . str_replace(
            '%23',
            '#',
            rawurlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">'
                . '<rect width="50" height="50" fill="#008000"/></svg>',
            ),
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image width="100" height="100" href="%s"/>', $uri),
        ));
        self::assertStringContainsString('0 0 50 50 re', $ops);
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
    }

    public function testANonSvgDataUriStillTakesTheRasterPath(): void
    {
        // A payload that merely mentions `<svg` inside other markup is
        // not an SVG document; it must not be handed to the SVG
        // painter (and, having no writer here, paints nothing).
        $uri = self::dataUri('<html><body>&lt;svg&gt;</body></html>');
        $ops = $this->paintOps(self::host(
            sprintf('<image width="100" height="100" href="%s"/>', $uri),
        ));
        self::assertSame('', $ops);
    }

    // ---------------------------------------------------------------
    // Positive cases.
    // ---------------------------------------------------------------

    public function testAnSvgDataUriPaintsTheReferencedTree(): void
    {
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image x="10" y="20" width="100" height="100" href="%s"/>', $uri),
        ));
        // Viewport placement, clip, then the viewBox-to-viewport scale.
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
        self::assertStringContainsString('0 0 100 100 re', $ops);
        self::assertStringContainsString('W', $ops);
        self::assertStringContainsString('2 0 0 2 0 0 cm', $ops);
        self::assertStringContainsString('0 0 50 50 re', $ops);
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
    }

    public function testAnOmittedHeightComesFromTheReferencedIntrinsicRatio(): void
    {
        // SVG 2 §8.6 / CSS Images 3 §5.3 — `width` with `height`
        // omitted derives the height from the referenced document's
        // intrinsic ratio (here 50:50 = 1:1).
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image width="1.5" href="%s"/>', $uri),
        ));
        self::assertStringContainsString('0 0 1.5 1.5 re', $ops);
    }

    public function testAnOmittedWidthComesFromTheReferencedIntrinsicRatio(): void
    {
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="40">'
            . '<rect width="80" height="40" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image height="10" href="%s"/>', $uri),
        ));
        self::assertStringContainsString('0 0 20 10 re', $ops);
    }

    public function testTheReferencedRootsPreserveAspectRatioGovernsWhenTheImageDeclaresNone(): void
    {
        // `xMinYMin meet` into a wider-than-tall viewport must anchor
        // at the left edge, NOT centre the way the default
        // `xMidYMid` would.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMinYMin">'
            . '<rect width="100" height="100" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image width="101.2" height="100" href="%s"/>', $uri),
        ));
        self::assertStringContainsString('1 0 0 1 0 0 cm', $ops);
        self::assertStringNotContainsString('1 0 0 1 0.6 0 cm', $ops);
    }

    public function testAnExplicitPreserveAspectRatioOnTheImageOverridesTheReferencedRoot(): void
    {
        // `none` scales the axes independently: 200/100 = 2 across,
        // 300/100 = 3 down.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<rect width="100" height="100" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf(
                '<image preserveAspectRatio="none" width="200" height="300" href="%s"/>',
                $uri,
            ),
        ));
        self::assertStringContainsString('2 0 0 3 0 0 cm', $ops);
    }

    public function testAViewFragmentSuppliesTheViewBoxAndPreserveAspectRatio(): void
    {
        // SVG 2 §18.3 — `…#view` activates that `<view>`, whose
        // viewBox stands in for the root's.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">'
            . '<view id="v" preserveAspectRatio="none" viewBox="0 0 50 100"/>'
            . '<rect width="50" height="100" fill="green"/></svg>',
        ) . '#v';
        $ops = $this->paintOps(self::host(
            sprintf('<image width="50" height="100" href="%s"/>', $uri),
        ));
        // Without the view the root's 1000x1000 viewBox would give a
        // 0.05 scale; with it the mapping is 1:1.
        self::assertStringContainsString('1 0 0 1 0 0 cm', $ops);
        self::assertStringNotContainsString('0.05 0 0 0.05', $ops);
    }

    public function testAReferencedDocumentWithoutIntrinsicDimensionsLaysOutAtTheImageViewport(): void
    {
        // No viewBox and no width/height: percentages inside the
        // referenced document resolve against the image box.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="50%" height="50%" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image width="200" height="200" href="%s"/>', $uri),
        ));
        self::assertStringContainsString('1 0 0 1 0 0 cm', $ops);
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }

    public function testDimensionlessImageAndResourceFillTheNearestViewport(): void
    {
        // SVG 2 §8.6 — `width` / `height` on `<image>` are `auto`, and
        // a resource with no intrinsic size has no concrete object
        // size of its own, so the default sizing algorithm falls back
        // to the default object size: the nearest SVG viewport.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="100" height="100" fill="green"/></svg>',
        );
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . sprintf('<image href="%s"/>', $uri)
            . '</svg>',
        );
        self::assertStringContainsString('0 0 100 100 re', $ops);
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
    }

    public function testADimensionlessImageContainsAResourceThatOnlyHasARatio(): void
    {
        // Intrinsic ratio but no intrinsic size: the concrete object
        // size is the largest rectangle of that ratio fitting the
        // default object size (a contain constraint), so a 2:1
        // resource in a 100x100 viewport is 100x50.
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100" preserveAspectRatio="none">'
            . '<rect width="200" height="100" fill="green"/></svg>',
        );
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . sprintf('<image href="%s"/>', $uri)
            . '</svg>',
        );
        self::assertStringContainsString('0 0 100 50 re', $ops);
    }

    public function testTheReferencedContentIsClippedToTheImageViewport(): void
    {
        $uri = self::dataUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" preserveAspectRatio="none">'
            . '<rect width="500" height="500" fill="green"/></svg>',
        );
        $ops = $this->paintOps(self::host(
            sprintf('<image width="40" height="60" href="%s"/>', $uri),
        ));
        self::assertMatchesRegularExpression('/0 0 40 60 re\nW\nn/', $ops);
    }
}
