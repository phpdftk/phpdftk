<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\Html\Parser as HtmlParser;

/**
 * Benchmarks for {@see BoxGenerator}. Tracks end-to-end performance of
 * parsing HTML + CSS, running the cascade against every element, and
 * emitting the box tree — the hot path that every html-to-pdf render
 * walks before layout begins.
 *
 * Fixtures grow in element count to make regression slope visible at
 * different scales (small blog post → moderate article → large doc).
 */
#[Bench\Iterations(3)]
#[Bench\Revs(5)]
class BoxGeneratorBench
{
    private CssParser $cssParser;
    private HtmlParser $htmlParser;
    private BoxGenerator $generator;
    private string $uaCss;

    public function __construct()
    {
        $this->cssParser = new CssParser();
        $this->htmlParser = new HtmlParser();
        $this->generator = new BoxGenerator(new Cascade(PropertyRegistry::default()));
        $this->uaCss = <<<CSS
            html, body, div, p, section, article, h1, h2, h3, ul, li {
                display: block;
            }
            span, a, em, strong, b, i, code {
                display: inline;
            }
            img {
                display: inline-block;
            }
            table { display: table; }
            tr { display: table-row; }
            td { display: table-cell; }
        CSS;
    }

    public function benchSmallBlogPost(): void
    {
        $html = $this->blogPost(10);
        $this->run($html);
    }

    public function benchMediumArticle(): void
    {
        $html = $this->blogPost(100);
        $this->run($html);
    }

    public function benchLargeDocumentationPage(): void
    {
        $html = $this->blogPost(500);
        $this->run($html);
    }

    public function benchTableGrid(): void
    {
        // Exercises the CSS 2.1 §17.2.1 anonymous table-object fixup:
        // rows lacking cells / cells lacking rows synthesise anonymous
        // wrappers on the box-generation hot path.
        $html = $this->tableGrid(100);
        $this->run($html);
    }

    public function benchInlineSvgUseSprites(): void
    {
        // The `<use href="#icon">` sprite idiom on the box-generation
        // hot path. Box generation is where an inline `<svg>` subtree is
        // cascaded, and where each `<use>` instance is cloned and
        // cascaded a second time (SVG 2 §5.6 shadow tree), so a sprite
        // sheet is the shape that pays for that twice over.
        $this->run($this->inlineSvgSprites(200));
    }

    public function benchInlineSvgWithoutUse(): void
    {
        // Same element count with no `<use>` at all — the baseline that
        // isolates the shadow-tree cost from the cost of cascading an
        // inline `<svg>` subtree in the first place.
        $this->run($this->inlineSvgFlat(200));
    }

    private function run(string $html): void
    {
        $doc = $this->htmlParser->parseDocument($html);
        $sheet = $this->cssParser->parseStylesheet($this->uaCss, Origin::UserAgent);
        $this->generator->generate($doc, [$sheet]);
    }

    private function inlineSvgSprites(int $instances): string
    {
        $body = '<svg width="800" height="800" xmlns="http://www.w3.org/2000/svg">'
            . '<defs><g id="icon">'
            . '<rect width="16" height="16"/>'
            . '<circle cx="8" cy="8" r="6"/>'
            . '<path d="M2 2 L14 14"/>'
            . '</g></defs>';
        for ($i = 0; $i < $instances; $i++) {
            $body .= sprintf(
                '<use href="#icon" x="%d" y="%d" fill="currentColor"/>',
                ($i % 40) * 20,
                intdiv($i, 40) * 20,
            );
        }
        $body .= '</svg>';
        return '<!DOCTYPE html><html><body>' . $body . '</body></html>';
    }

    private function inlineSvgFlat(int $groups): string
    {
        $body = '<svg width="800" height="800" xmlns="http://www.w3.org/2000/svg">';
        for ($i = 0; $i < $groups; $i++) {
            $body .= sprintf(
                '<g transform="translate(%d %d)" fill="currentColor">'
                . '<rect width="16" height="16"/>'
                . '<circle cx="8" cy="8" r="6"/>'
                . '<path d="M2 2 L14 14"/>'
                . '</g>',
                ($i % 40) * 20,
                intdiv($i, 40) * 20,
            );
        }
        $body .= '</svg>';
        return '<!DOCTYPE html><html><body>' . $body . '</body></html>';
    }

    private function tableGrid(int $tables): string
    {
        // Each table has bare `display: table-cell` divs directly under
        // a `display: table` — no `<tr>` — so its cells are swept into a
        // synthesised anonymous table-row by the §17.2.1 fixup.
        $body = '';
        for ($i = 0; $i < $tables; $i++) {
            $body .= sprintf(
                '<div style="display: table">'
                . '<div style="display: table-cell">A%d</div>'
                . '<div style="display: table-cell">B%d</div>'
                . '<div style="display: table-cell">C%d</div>'
                . '</div>',
                $i,
                $i,
                $i,
            );
        }
        return '<!DOCTYPE html><html><body>' . $body . '</body></html>';
    }

    private function blogPost(int $sections): string
    {
        $body = '';
        for ($i = 0; $i < $sections; $i++) {
            $body .= sprintf(
                '<section><h2>Section %d</h2><p>Some <span>inline</span> body text with <em>emphasis</em> and <strong>strong</strong>.</p></section>',
                $i,
            );
        }
        return '<!DOCTYPE html><html><body>' . $body . '</body></html>';
    }
}
