<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Layout\FontFace;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;

/**
 * Benchmarks for the full {@see Renderer} pipeline — parse, cascade, box
 * generation, layout, paint, multi-page emission. Complements
 * {@see BoxGeneratorBench} which stops before layout / paint.
 *
 * Tracks the project-target ceiling of < 500 ms cold / < 200 ms warm for
 * the realistic-business-doc fixture (`phpdftk/html-to-pdf` performance
 * ceiling per `docs/plans/html-and-svg.md`).
 *
 * Fixtures grow in content size to make regression slope visible.
 */
#[Bench\Iterations(3)]
#[Bench\Revs(3)]
class RendererBench
{
    private Renderer $renderer;

    public function __construct()
    {
        $this->renderer = new Renderer(new RendererOptions());
    }

    public function benchShortDocument(): void
    {
        // ~10 sections — a typical landing-page / cover-letter sized doc.
        $this->renderer->render($this->document(10));
    }

    public function benchMediumArticle(): void
    {
        // ~50 sections — a feature article / spec page.
        $this->renderer->render($this->document(50));
    }

    public function benchLongReport(): void
    {
        // ~200 sections — a quarterly report / book chapter. With the
        // off-page-skip optimisation in the painter, this fits in ~12 MB.
        $this->renderer->render($this->document(200));
    }

    public function benchRealFaceMatching(): void
    {
        // Exercises the CSS Fonts 4 weight/style matching path: a doc
        // densely peppered with `<strong>` / `<em>` against a registered
        // 4-face Inter family (regular/bold × normal/italic). Regressions
        // in FontResolver::resolveMatch show up here.
        $fontPath = __DIR__ . '/../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            return;
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer(
            (new RendererOptions())
                ->withDefaultFont($regular)
                ->withFontFaces([
                    'Inter' => [
                        new FontFace($regular, 400, 'normal'),
                        new FontFace($regular, 400, 'italic'),
                        new FontFace($regular, 700, 'normal'),
                        new FontFace($regular, 700, 'italic'),
                    ],
                ]),
        );
        $body = '';
        for ($i = 0; $i < 50; $i++) {
            $body .= sprintf(
                '<p style="font-family: Inter">Section %d — '
                . '<strong>bold</strong>, <em>italic</em>, '
                . '<strong><em>bold italic</em></strong>, normal again.</p>',
                $i,
            );
        }
        $renderer->render('<!DOCTYPE html><html><body>' . $body . '</body></html>');
    }

    public function benchPageMarginBoxes(): void
    {
        // Exercises CSS Paged Media 3 §3.6 nested margin-box rendering
        // across a multi-page document. Each page re-paints all 6
        // header/footer strings, so regressions in collectPageMarginBoxes
        // or paintPageMarginBoxes show up at scale.
        $fontPath = __DIR__ . '/../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            return;
        }
        $font = (new OpenTypeParser($fontPath))->parse();
        $renderer = new Renderer((new RendererOptions())->withDefaultFont($font));
        $css = '@page { '
            . '@top-left { content: "Acme"; } @top-center { content: "Quarterly Report"; } '
            . '@top-right { content: "Q3"; } @bottom-left { content: "Confidential"; } '
            . '@bottom-center { content: "phpdftk"; } @bottom-right { content: "DRAFT"; } }';
        $body = '';
        for ($i = 0; $i < 30; $i++) {
            $body .= sprintf(
                '<section style="page-break-after: always"><h1>Section %d</h1>'
                . '<p>Body content for section.</p></section>',
                $i,
            );
        }
        $renderer->render(
            '<!DOCTYPE html><html><head><style>' . $css . '</style></head>'
            . '<body>' . $body . '</body></html>',
        );
    }

    public function benchMultiColumn(): void
    {
        // Exercises the CSS Multi-column 1 path: two-pass layout (virtual
        // single-column → balance → redistribute) plus the painter's
        // per-rule stroke pass. ~80 paragraphs feeds the balance
        // algorithm enough children to make the redistribute pass
        // meaningful.
        $body = '';
        for ($i = 0; $i < 80; $i++) {
            $body .= sprintf(
                '<p>Section %d — column content with <strong>emphasis</strong> '
                . 'and <em>variety</em>. Multi-column layout balances heights '
                . 'and strokes a rule between columns.</p>',
                $i,
            );
        }
        $this->renderer->render(
            '<!DOCTYPE html><html><head><style>'
            . 'section { columns: 3; column-gap: 16pt; column-rule: 1pt solid #888; }'
            . 'p { margin: 0 0 8pt 0; }'
            . '</style></head><body><section>' . $body . '</section></body></html>',
        );
    }

    public function benchRichTypography(): void
    {
        // Exercises inline-layout paths: text-transform, letter-spacing,
        // text-indent, vertical-align sub/sup, fake-bold/italic, inline
        // styles, mixed font sizes.
        $body = '';
        for ($i = 0; $i < 50; $i++) {
            $body .= sprintf(
                '<p style="text-indent: 1em; letter-spacing: 0.5px;">Section %d — '
                . 'with <b>bold</b>, <i>italic</i>, <sup>sup</sup>, <sub>sub</sub>, '
                . '<span style="font-size: 1.5em">large</span>, '
                . '<span style="text-transform: uppercase">caps</span>, '
                . 'and an <a href="#x">internal link</a>.</p>',
                $i,
            );
        }
        $html = '<!DOCTYPE html><html><head><title>Typography Bench</title></head>'
            . '<body><h1 id="x">Typography Bench</h1>' . $body . '</body></html>';
        $this->renderer->render($html);
    }

    private function document(int $sections): string
    {
        $body = '';
        for ($i = 0; $i < $sections; $i++) {
            $body .= sprintf(
                '<section><h2>Section %d</h2>'
                . '<p>Body text with <span>inline</span> and <em>emphasis</em>.</p>'
                . '<ul><li>Item one</li><li>Item two</li><li>Item three</li></ul>'
                . '</section>',
                $i,
            );
        }
        return '<!DOCTYPE html><html><head><title>Bench Document</title></head>'
            . '<body>' . $body . '</body></html>';
    }
}
