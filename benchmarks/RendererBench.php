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

    public function benchFloats(): void
    {
        // Exercises CSS 2.1 §9.5: a left float at the top of each
        // section + a long paragraph wrapping around it. Stresses
        // FloatContext queries inside InlineLayout's per-line bound
        // computation across many lines.
        $body = '';
        for ($i = 0; $i < 40; $i++) {
            $body .= sprintf(
                '<section>'
                . '<div class="thumb" style="float: left; width: 80pt; height: 60pt; background-color: #eef; margin: 0 12pt 6pt 0"></div>'
                . '<p>Section %d body text — wraps around the floated thumbnail '
                . 'on the left. Subsequent lines past the thumbnail bottom '
                . 'resume at the container edge.</p>'
                . '<div style="clear: both"></div>'
                . '</section>',
                $i,
            );
        }
        $this->renderer->render(
            '<!DOCTYPE html><html><head><style>'
            . 'section { margin-bottom: 12pt; }'
            . 'p { margin: 0; }'
            . '</style></head><body>' . $body . '</body></html>',
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

    public function benchFlex(): void
    {
        // Exercises CSS Flexible Box Layout 1 §9: container measures
        // each item (first pass), then redistributes per
        // justify-content and aligns vertically per align-items.
        // 40 flex rows × 4 items each pushes the per-item second pass
        // through enough iterations to make the bench meaningful.
        $body = '';
        for ($i = 0; $i < 40; $i++) {
            $body .= '<div class="row">'
                . '<div class="card">A</div>'
                . '<div class="card">B</div>'
                . '<div class="card">C</div>'
                . '<div class="card">D</div>'
                . '</div>';
        }
        $this->renderer->render(
            '<!DOCTYPE html><html><head><style>'
            . '.row { display: flex; justify-content: space-between; column-gap: 8pt; margin-bottom: 8pt; }'
            . '.card { width: 100pt; height: 40pt; background-color: #eef; padding: 4pt; }'
            . '</style></head><body>' . $body . '</body></html>',
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

    public function benchPhase2Grid(): void
    {
        // Phase-2 Grid MVP: 50 explicit-placement cells in a 5-col
        // grid exercises the placement resolver + auto-flow loop +
        // per-cell layout pass.
        $items = '';
        for ($i = 0; $i < 50; $i++) {
            $items .= '<div class="cell"></div>';
        }
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: 40pt 40pt 40pt 40pt 40pt; '
            . '        grid-template-rows: 30pt 30pt 30pt 30pt 30pt 30pt 30pt 30pt 30pt 30pt; '
            . '        column-gap: 4pt; row-gap: 4pt; }'
            . '.cell { background-color: #ccc; }'
            . '</style></head><body><div class="grid">' . $items . '</div></body></html>';
        $this->renderer->render($html);
    }

    public function benchPhase2GridAdvanced(): void
    {
        // Phase-2 Grid advanced — exercises fr resolution, repeat()
        // expansion, span placement and justify-self alignment so
        // regressions in any of the four show up here.
        $items = '';
        for ($i = 0; $i < 40; $i++) {
            $cls = ($i % 5 === 0) ? 'span' : 'cell';
            $items .= '<div class="' . $cls . '"></div>';
        }
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: 80pt repeat(4, 1fr); '
            . '        grid-template-rows: repeat(10, 30pt); '
            . '        column-gap: 4pt; row-gap: 4pt; }'
            . '.cell { background-color: #ccc; }'
            . '.span { grid-column: span 2; background-color: #def; '
            . '        justify-self: center; width: 60pt; }'
            . '</style></head><body><div class="grid">' . $items . '</div></body></html>';
        $this->renderer->render($html);
    }

    public function benchPhase2Transform3d(): void
    {
        // Phase-2 3D transforms — exercises rotateX/Y/Z flattening,
        // matrix3d entry extraction, and backface-visibility check
        // across many cards.
        $body = '';
        for ($i = 0; $i < 30; $i++) {
            $rotX = ($i * 13) % 180;
            $rotY = ($i * 17) % 180;
            $body .= sprintf(
                '<div class="card" style="transform: rotateX(%ddeg) rotateY(%ddeg); '
                . 'backface-visibility: hidden;"></div>',
                $rotX,
                $rotY,
            );
        }
        $html = '<html><head><style>'
            . '.card { display: inline-block; width: 40pt; height: 30pt; '
            . '        background-color: #336699; margin: 4pt; }'
            . '</style></head><body>' . $body . '</body></html>';
        $this->renderer->render($html);
    }

    public function benchPhase2GridAutoFlow(): void
    {
        // Phase-2 Grid auto-flow column + dense — 60 items in a
        // sparse column-major grid with one tall spanner forcing
        // dense backfill on each render exercises both modes.
        $items = '<div class="tall"></div>';
        for ($i = 0; $i < 59; $i++) {
            $items .= '<div class="cell"></div>';
        }
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: repeat(6, 40pt); '
            . '        grid-template-rows: 20pt 20pt 20pt; '
            . '        grid-auto-flow: row dense; '
            . '        grid-auto-rows: 20pt; '
            . '        column-gap: 4pt; row-gap: 4pt; }'
            . '.cell { background-color: #ccc; }'
            . '.tall { grid-row: 1 / span 2; background-color: #999; }'
            . '</style></head><body><div class="grid">' . $items . '</div></body></html>';
        $this->renderer->render($html);
    }

    public function benchPhase2GridImplicitRows(): void
    {
        // Phase-2 Grid implicit-row growth — 100 cells in a 4-column
        // grid with only 1 explicit row exercises the growth loop
        // through 25 implicit rows on every render.
        $items = '';
        for ($i = 0; $i < 100; $i++) {
            $items .= '<div class="cell"></div>';
        }
        $html = '<html><head><style>'
            . '.grid { display: grid; '
            . '        grid-template-columns: repeat(4, 40pt); '
            . '        grid-template-rows: 25pt; '
            . '        grid-auto-rows: 25pt; '
            . '        column-gap: 4pt; row-gap: 4pt; }'
            . '.cell { background-color: #ccc; }'
            . '</style></head><body><div class="grid">' . $items . '</div></body></html>';
        $this->renderer->render($html);
    }

    public function benchPhase2GridTemplateAreas(): void
    {
        // Phase-2 Grid template-areas — exercises the area parser
        // (rectangle validation per name), name → line resolution,
        // and implicit track-count derivation. 6 nested holy-grail
        // layouts in a single document to give the hot path enough
        // to chew on.
        $body = '';
        for ($i = 0; $i < 6; $i++) {
            $body .= '<div class="app">'
                . '<div class="head"></div>'
                . '<div class="side"></div>'
                . '<div class="main"></div>'
                . '<div class="foot"></div>'
                . '</div>';
        }
        $html = '<html><head><style>'
            . '.app { display: grid; '
            . '       grid-template-areas: "head head" "side main" "foot foot"; '
            . '       grid-template-columns: 80pt 1fr; '
            . '       grid-template-rows: 30pt 80pt 30pt; '
            . '       column-gap: 4pt; row-gap: 4pt; height: 144pt; }'
            . '.head { grid-area: head; background-color: #336699; }'
            . '.side { grid-area: side; background-color: #99ccff; }'
            . '.main { grid-area: main; background-color: #eeeeff; }'
            . '.foot { grid-area: foot; background-color: #336699; }'
            . '</style></head><body>' . $body . '</body></html>';
        $this->renderer->render($html);
    }

    public function benchPhase2Gradients(): void
    {
        // Phase-2: N-stop gradients route through a Type-3 stitching
        // function. 30 elements × ~5-stop gradients exercises both
        // the stop normalisation and the PDF function-tree emission.
        $body = '';
        for ($i = 0; $i < 30; $i++) {
            $body .= '<div style="height: 24pt; background-image: '
                . 'linear-gradient(red, yellow 25%, lime 50%, aqua 75%, blue);">'
                . '</div>';
        }
        $this->renderer->render('<html><body>' . $body . '</body></html>');
    }

    public function benchPhase2BorderCollapseHeavy(): void
    {
        // Phase-2: border-collapse conflict resolution runs per joint
        // (O(cells) + O(rim)). A 20×10 table with mixed border widths
        // and explicit table-border vs cell-border exercises both
        // inner-joint and outer-table-border resolution paths.
        $rows = '';
        for ($r = 0; $r < 20; $r++) {
            $cells = '';
            for ($c = 0; $c < 10; $c++) {
                $w = ($c % 3 === 0) ? 4 : 1;
                $cells .= '<td style="border: ' . $w . 'pt solid black;">x</td>';
            }
            $rows .= '<tr>' . $cells . '</tr>';
        }
        $html = '<html><body><table style="display: table; '
            . 'border: 6pt solid black; border-collapse: collapse;">'
            . $rows . '</table></body></html>';
        $this->renderer->render($html);
    }

    public function benchPhase2MediaQueriesScale(): void
    {
        // Phase-2: every @media block runs feature-query evaluation
        // per declaration. 100 sheets each with a @media gate ensures
        // the cascade walks the conditional path repeatedly.
        $css = '';
        for ($i = 0; $i < 100; $i++) {
            $css .= '@media (min-width: ' . $i . 'px) { '
                . '.s' . $i . ' { color: red; } }';
        }
        $body = '';
        for ($i = 0; $i < 50; $i++) {
            $body .= '<p class="s' . $i . '">Section ' . $i . '</p>';
        }
        $html = '<html><head><style>' . $css . '</style></head>'
            . '<body>' . $body . '</body></html>';
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
