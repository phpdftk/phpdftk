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

    private function run(string $html): void
    {
        $doc = $this->htmlParser->parseDocument($html);
        $sheet = $this->cssParser->parseStylesheet($this->uaCss, Origin::UserAgent);
        $this->generator->generate($doc, [$sheet]);
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
