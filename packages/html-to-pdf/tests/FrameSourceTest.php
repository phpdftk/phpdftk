<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests;

use Phpdftk\Filesystem\ResourceLoader;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\FrameSource;
use PHPUnit\Framework\TestCase;

/**
 * HTML §4.8.5 — which bytes an `<iframe>` / `<frame>` embeds.
 *
 * The negative cases carry most of the weight here. A frame that
 * resolves to the WRONG bytes is worse than one that resolves to
 * nothing: handing a PNG to the HTML parser paints a frame full of
 * mojibake, and reaching out to the network mid-layout would be an SSRF
 * vector. Each such refusal gets a test so it cannot be relaxed silently.
 */
final class FrameSourceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phpdftk-frame-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
    }

    private function frame(string $markup, string $prologue = '<!doctype html><body>'): \Phpdftk\Html\Dom\Element
    {
        $document = (new HtmlParser())->parseDocument($prologue . $markup);
        $found = null;
        $walk = static function (object $node) use (&$walk, &$found): void {
            if ($found !== null) {
                return;
            }
            if ($node instanceof \Phpdftk\Html\Dom\Element
                && in_array(strtolower($node->localName), ['iframe', 'frame', 'div'], true)
            ) {
                $found = $node;
                if (strtolower($node->localName) !== 'div') {
                    return;
                }
            }
            foreach ($node->childNodes() as $child) {
                $walk($child);
            }
        };
        $walk($document);
        self::assertNotNull($found, 'fixture markup produced no frame element');
        return $found;
    }

    private function source(?string $baseDir = null): FrameSource
    {
        return new FrameSource(new ResourceLoader($baseDir, $baseDir));
    }

    public function testSrcdocIsTheDocumentVerbatim(): void
    {
        self::assertSame(
            '<p>hi</p>',
            $this->source()->markupFor($this->frame('<iframe srcdoc="<p>hi</p>"></iframe>')),
        );
    }

    public function testSrcdocWinsOverSrc(): void
    {
        self::assertSame(
            'inline',
            $this->source()->markupFor(
                $this->frame('<iframe srcdoc="inline" src="data:text/html,url"></iframe>'),
            ),
        );
    }

    public function testEmptySrcdocIsAnEmptyDocumentNotAFallbackToSrc(): void
    {
        // `srcdoc=""` embeds a real, empty document. Falling through to
        // `src` here would make an author's deliberate blanking of a
        // frame silently re-load the URL it was blanking.
        self::assertSame(
            '',
            $this->source()->markupFor(
                $this->frame('<iframe srcdoc="" src="data:text/html,should-not-load"></iframe>'),
            ),
        );
    }

    public function testDataTextHtmlIsPercentDecoded(): void
    {
        self::assertSame(
            '<body marginwidth=\'100\'>x</body>',
            $this->source()->markupFor(
                $this->frame('<iframe src="data:text/html,%3Cbody%20marginwidth%3D%27100%27%3Ex%3C/body%3E"></iframe>'),
            ),
        );
    }

    public function testDataUrlWithCharsetParameterStillCountsAsHtml(): void
    {
        self::assertSame(
            '<p>x</p>',
            $this->source()->markupFor(
                $this->frame('<iframe src="data:text/html;charset=utf-8,<p>x</p>"></iframe>'),
            ),
        );
    }

    public function testAboutBlankIsAnEmptyDocument(): void
    {
        self::assertSame(
            '',
            $this->source()->markupFor($this->frame('<iframe src="about:blank"></iframe>')),
        );
    }

    public function testNonMarkupDataUrlIsRefused(): void
    {
        // A `data:image/png` frame is an IMAGE document. Parsing its
        // bytes as markup paints `\x89PNG IHDR…` as visible text.
        self::assertNull(
            $this->source()->markupFor(
                $this->frame('<iframe src="data:image/png;base64,iVBORw0KGgo="></iframe>'),
            ),
        );
    }

    public function testBareDataUrlWithNoMimeIsRefused(): void
    {
        // rfc2397 defaults a MIME-less `data:` to text/plain.
        self::assertNull(
            $this->source()->markupFor($this->frame('<iframe src="data:,hello"></iframe>')),
        );
    }

    public function testHttpSrcIsRefused(): void
    {
        // No network access during layout, ever.
        self::assertNull(
            $this->source($this->dir)->markupFor(
                $this->frame('<iframe src="http://example.invalid/page.html"></iframe>'),
            ),
        );
    }

    public function testMissingSrcIsRefused(): void
    {
        self::assertNull($this->source()->markupFor($this->frame('<iframe></iframe>')));
    }

    public function testEmptySrcIsRefused(): void
    {
        self::assertNull($this->source()->markupFor($this->frame('<iframe src="  "></iframe>')));
    }

    public function testNonFrameElementIsRefused(): void
    {
        self::assertNull(
            $this->source()->markupFor($this->frame('<div srcdoc="<p>x</p>"></div>')),
        );
    }

    public function testLocalHtmlFileLoads(): void
    {
        file_put_contents($this->dir . '/inner.html', '<p>from disk</p>');
        self::assertSame(
            '<p>from disk</p>',
            $this->source($this->dir)->markupFor(
                $this->frame('<iframe src="inner.html"></iframe>'),
            ),
        );
    }

    public function testLocalHtmlFileWithQueryStringLoads(): void
    {
        file_put_contents($this->dir . '/inner.html', '<p>from disk</p>');
        self::assertSame(
            '<p>from disk</p>',
            $this->source($this->dir)->markupFor(
                $this->frame('<iframe src="inner.html?v=2"></iframe>'),
            ),
        );
    }

    public function testFragmentOnlySrcIsRefused(): void
    {
        // `#frag` is a same-document reference, not a file named `frag`.
        file_put_contents($this->dir . '/frag', '<p>nope</p>');
        self::assertNull(
            $this->source($this->dir)->markupFor($this->frame('<iframe src="#frag"></iframe>')),
        );
    }

    public function testLocalNonMarkupFileIsRefusedWithoutBeingRead(): void
    {
        // The regression this guards: `<iframe src="green.png">` used to
        // hand the PNG's bytes to the HTML parser and paint them as text.
        file_put_contents($this->dir . '/green.png', "\x89PNG\r\n\x1a\nIHDR");
        self::assertNull(
            $this->source($this->dir)->markupFor(
                $this->frame('<iframe src="green.png"></iframe>'),
            ),
        );
    }

    public function testLocalPathEscapingTheSandboxIsRefused(): void
    {
        file_put_contents($this->dir . '/inner.html', '<p>ok</p>');
        self::assertNull(
            $this->source($this->dir)->markupFor(
                $this->frame('<iframe src="../../../../etc/hosts.html"></iframe>'),
            ),
        );
    }

    public function testStreamWrapperSrcIsRefused(): void
    {
        self::assertNull(
            $this->source($this->dir)->markupFor(
                $this->frame('<iframe src="php://input"></iframe>'),
            ),
        );
    }

    public function testLocalPathWithNoLoaderConfiguredIsRefused(): void
    {
        self::assertNull(
            (new FrameSource())->markupFor($this->frame('<iframe src="inner.html"></iframe>')),
        );
    }

    public function testFrameElementIsSupportedAlongsideIframe(): void
    {
        // `<frame>` is only parsed inside a `<frameset>` — a bare one in
        // `<body>` is dropped by the tree builder, so the fixture needs
        // the frameset around it.
        self::assertSame(
            '<p>x</p>',
            $this->source()->markupFor($this->frame(
                '<frame srcdoc="<p>x</p>">',
                '<!doctype html><html><frameset>',
            )),
        );
    }
}
