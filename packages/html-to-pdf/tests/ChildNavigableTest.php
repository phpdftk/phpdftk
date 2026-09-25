<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests;

use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Layout\LayoutContext;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use PHPUnit\Framework\TestCase;

/**
 * HTML §7.3 — an `<iframe>` hosts a child navigable, and its embedded
 * document's box tree hangs off the frame box.
 *
 * These exercise the whole composition (source resolution → nested box
 * generation → layout) rather than any one piece, because the failure
 * that motivated it only showed up at the join: box generation attached
 * the embedded tree correctly, and layout then threw it away on the
 * grounds that a frame is a replaced element and a replaced element's
 * children are fallback content.
 */
final class ChildNavigableTest extends TestCase
{
    /** Generate + expand + lay out `$html`, returning the root box. */
    private function layoutDocument(string $html): Box
    {
        $renderer = new Renderer(new RendererOptions());
        $class = new \ReflectionClass($renderer);

        $document = $renderer->parse($html);
        $collect = $class->getMethod('collectStylesheets');
        $sheets = $collect->invoke($renderer, null, $document);

        $generator = $class->getProperty('boxGenerator')->getValue($renderer);
        $root = $generator->generate($document, $sheets);
        self::assertNotNull($root, 'document produced no root box');

        $markup = [];
        $class->getMethod('expandChildNavigables')->invokeArgs(
            $renderer,
            [$root, 0, &$markup],
        );

        $class->getProperty('layout')->getValue($renderer)->layout(
            $root,
            new LayoutContext(612, 792, 0, 0, new LengthContext(612, 792)),
        );
        return $root;
    }

    /**
     * Every box in `$root`'s subtree whose element is `$tag`.
     *
     * `TextBox`es carry their CONTAINING element, so a `<p>x</p>` yields
     * both the paragraph's own box and the text box inside it; only the
     * former is the element's box.
     *
     * @return list<Box>
     */
    private function boxesFor(Box $root, string $tag): array
    {
        $out = [];
        $walk = static function (Box $box) use (&$walk, &$out, $tag): void {
            if ($box->element !== null
                && !$box instanceof \Phpdftk\HtmlToPdf\Box\TextBox
                && strtolower($box->element->localName) === $tag
            ) {
                $out[] = $box;
            }
            foreach ($box->children as $child) {
                $walk($child);
            }
        };
        $walk($root);
        return $out;
    }

    private function frame(Box $root): Box
    {
        $frames = $this->boxesFor($root, 'iframe');
        self::assertCount(1, $frames);
        return $frames[0];
    }

    public function testFrameUsesTheDefaultObjectSizeWhenNothingSaysOtherwise(): void
    {
        // HTML §15.3.3 — a frame has no intrinsic size, so CSS Images 3
        // §5.3 falls back to 300x150. Before this the frame laid out at
        // zero and the embedded document had nowhere to go.
        $frame = $this->frame($this->layoutDocument(
            '<!doctype html><body><iframe srcdoc="<p>x</p>"></iframe>',
        ));
        self::assertSame(300.0, $frame->geometry->width);
        self::assertSame(150.0, $frame->geometry->height);
    }

    public function testWidthAndHeightAttributesOverrideTheDefaultObjectSize(): void
    {
        $frame = $this->frame($this->layoutDocument(
            '<!doctype html><body><iframe width="500" height="200" srcdoc="<p>x</p>"></iframe>',
        ));
        self::assertSame(500.0, $frame->geometry->width);
        self::assertSame(200.0, $frame->geometry->height);
    }

    public function testAuthorCssOverridesTheDefaultObjectSize(): void
    {
        $frame = $this->frame($this->layoutDocument(
            '<!doctype html><style>iframe { width: 400px; height: 120px }</style>'
            . '<body><iframe srcdoc="<p>x</p>"></iframe>',
        ));
        self::assertSame(400.0, $frame->geometry->width);
        self::assertSame(120.0, $frame->geometry->height);
    }

    public function testEmbeddedDocumentBecomesAChildOfTheFrame(): void
    {
        $frame = $this->frame($this->layoutDocument(
            '<!doctype html><body><iframe srcdoc="<p>embedded</p>"></iframe>',
        ));
        self::assertTrue($frame->hostsChildNavigable);
        self::assertCount(1, $frame->children);
        $embeddedRoot = $frame->children[0];
        self::assertNotNull($embeddedRoot->element);
        self::assertSame('html', strtolower($embeddedRoot->element->localName));
    }

    public function testEmbeddedContentIsLaidOutInsideTheFrame(): void
    {
        // The regression guard: layout used to skip a replaced element's
        // children, so the embedded tree existed but never got geometry.
        $root = $this->layoutDocument(
            '<!doctype html><body style="margin:0"><iframe srcdoc="<p>embedded</p>"></iframe>',
        );
        $frame = $this->frame($root);
        $bodies = $this->boxesFor($frame, 'body');
        self::assertCount(1, $bodies, 'embedded <body> must be laid out');
        $embedded = $bodies[0];
        self::assertGreaterThan(0.0, $embedded->geometry->width);
        // Inside the frame's content box: past the 2px UA border.
        self::assertGreaterThanOrEqual($frame->geometry->x, $embedded->geometry->x);
        self::assertLessThanOrEqual(
            $frame->geometry->x + $frame->geometry->width,
            $embedded->geometry->x + $embedded->geometry->width,
        );
    }

    public function testEmbeddedBodyMarginAttributeIndentsTheEmbeddedDocument(): void
    {
        // This is what the `body-margin-*` reftests actually measure, and
        // it is unobservable unless the frame renders its document.
        $root = $this->layoutDocument(
            '<!doctype html><body style="margin:0">'
            . '<iframe srcdoc="<body marginwidth=\'100\'>x</body>"></iframe>',
        );
        $frame = $this->frame($root);
        $embedded = $this->boxesFor($frame, 'body')[0];
        // `geometry->x` is the CONTENT-box origin, so the frame's own 2px
        // UA border is already accounted for in `$frame->geometry->x`.
        self::assertEqualsWithDelta(
            $frame->geometry->x + 100.0,
            $embedded->geometry->x,
            0.5,
            'embedded body should be indented 100px inside the frame',
        );
    }

    public function testHostStylesDoNotCrossTheFrameBoundary(): void
    {
        // Styles are per-document. A host rule reaching into the frame
        // would be a navigable-boundary leak, not a nicety.
        $root = $this->layoutDocument(
            '<!doctype html><style>p { margin-left: 77px }</style>'
            . '<body style="margin:0"><iframe srcdoc="<p>x</p>"></iframe>',
        );
        $paragraphs = $this->boxesFor($this->frame($root), 'p');
        self::assertCount(1, $paragraphs);
        $frame = $this->frame($root);
        self::assertLessThan(
            $frame->geometry->x + 77.0,
            $paragraphs[0]->geometry->x,
            'host `p { margin-left }` must not apply inside the frame',
        );
    }

    public function testFrameWithNoResolvableSourceStaysEmpty(): void
    {
        $frame = $this->frame($this->layoutDocument(
            '<!doctype html><body><iframe src="http://example.invalid/x.html"></iframe>',
        ));
        self::assertFalse($frame->hostsChildNavigable);
        self::assertSame([], $frame->children);
        // Still a 300x150 replaced box — an unloadable frame is blank,
        // not absent.
        self::assertSame(300.0, $frame->geometry->width);
    }

    public function testNestedFramesExpandToTheDepthCapAndStop(): void
    {
        // Six levels of nesting; the cap is four. Without a cap a frame
        // that embeds itself recurses until the stack gives out.
        // Built with `data:` URLs rather than nested `srcdoc`, so each
        // level percent-encodes the one inside it and the quoting stays
        // unambiguous however deep it goes.
        $inner = '<p>deep</p>';
        for ($i = 0; $i < 6; $i++) {
            $inner = '<iframe src="data:text/html,' . rawurlencode($inner) . '"></iframe>';
        }
        $root = $this->layoutDocument('<!doctype html><body>' . $inner);
        $frames = $this->boxesFor($root, 'iframe');
        $expanded = array_values(array_filter(
            $frames,
            static fn(Box $f): bool => $f->hostsChildNavigable,
        ));
        // Four expansions, so five frame BOXES exist: the deepest one is
        // reached but left empty rather than recursed into.
        self::assertCount(4, $expanded, 'expansion must stop at MAX_FRAME_DEPTH');
        self::assertCount(5, $frames);
        self::assertSame([], $frames[4]->children);
    }

    public function testRenderingADocumentWithAFrameProducesAValidPdf(): void
    {
        $result = (new Renderer())->render(
            '<!doctype html><body><iframe srcdoc="<p>embedded</p>"></iframe>',
        );
        $bytes = $result->writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
    }
}
