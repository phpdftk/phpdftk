<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\Box\AnonymousBlockBox;
use Phpdftk\HtmlToPdf\Box\AtomicInlineBox;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use PHPUnit\Framework\TestCase;

/**
 * CSS 2.1 §9.2.1.1 — an inline box breaks around a block-level box
 * inside it, and the broken halves plus the block are wrapped in an
 * anonymous block.
 *
 * A foreign-content root (`<math>` / `<svg>`) is exempt. It is
 * REPLACED: its rendering comes from outside the CSS box tree and the
 * painter reaches it only through its atomic-inline box. The
 * descendant boxes generated beneath it are scaffolding for
 * measurement, not participants in the parent's inline formatting
 * context, so a block-level one among them must not break the root
 * apart. Promoting it to an anonymous block dropped the routing to
 * `paintInlineMath` / `paintInlineSvg` and the whole formula or
 * graphic vanished — which is what a `position: absolute` descendant
 * did to the `*-rendering-from-in-flow` MathML fixtures.
 */
final class InlineSplitsAroundInFlowBlockTest extends TestCase
{
    private BoxGenerator $generator;
    private CssParser $css;
    private HtmlParser $html;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $this->generator = new BoxGenerator(new Cascade(PropertyRegistry::default()));
    }

    private function tree(string $body, string $extraCss = ''): Box
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div, p { display: block; } span { display: inline; } '
            . 'math { display: inline-block; } ' . $extraCss,
        );
        $doc = $this->html->parseDocument('<html><body>' . $body . '</body></html>');
        return $this->generator->generate($doc, [$sheet]);
    }

    /** Principal box generated for the first element with this tag. */
    private function find(Box $root, string $tag): ?Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element !== null
                && strtolower($node->element->localName) === $tag
                && !$node instanceof \Phpdftk\HtmlToPdf\Box\TextBox
            ) {
                return $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    // ---------------------------------------------------------------
    // A replaced foreign root never splits around its own subtree.
    // ---------------------------------------------------------------

    public function testMathSurvivesAnAbsolutelyPositionedDescendant(): void
    {
        $box = $this->find(
            $this->tree(
                '<p><math><mrow><mo class="oof"></mo><mo>+</mo></mrow></math></p>',
                '.oof { position: absolute; }',
            ),
            'math',
        );
        self::assertInstanceOf(
            AtomicInlineBox::class,
            $box,
            'the <math> must stay an atomic inline so paintInlineMath sees it',
        );
    }

    public function testMathSurvivesAnInFlowBlockLevelDescendant(): void
    {
        $box = $this->find(
            $this->tree(
                '<p><math><mrow><mo class="blk"></mo><mo>+</mo></mrow></math></p>',
                '.blk { display: block; }',
            ),
            'math',
        );
        self::assertInstanceOf(AtomicInlineBox::class, $box);
    }

    public function testSvgSurvivesAnAbsolutelyPositionedDescendant(): void
    {
        $box = $this->find(
            $this->tree(
                '<p><svg><g class="oof"></g></svg></p>',
                'svg { display: inline-block; } .oof { position: absolute; }',
            ),
            'svg',
        );
        self::assertInstanceOf(AtomicInlineBox::class, $box);
    }

    // ---------------------------------------------------------------
    // Ordinary inlines are untouched by the exemption.
    // ---------------------------------------------------------------

    public function testInFlowBlockStillSplitsAnOrdinaryInline(): void
    {
        $box = $this->find(
            $this->tree(
                '<p><span>a<b class="blk">x</b>b</span></p>',
                '.blk { display: block; }',
            ),
            'span',
        );
        self::assertInstanceOf(AnonymousBlockBox::class, $box);
    }

    /**
     * §9.2.1.1 says the split is triggered by an IN-FLOW block, so an
     * absolutely positioned one should arguably leave the inline
     * intact. Our abs-pos collection currently REACHES those children
     * through the anonymous block the split creates, and removing the
     * split for ordinary inlines regresses seven BlockLayout cases
     * (abs-pos auto margins, vertical containing-block axes, RTL
     * shrink-wrap). The exemption is therefore scoped to replaced
     * foreign roots, whose subtrees never needed that path; this test
     * pins the deviation so it is visible rather than forgotten.
     */
    public function testAbsolutelyPositionedBlockStillSplitsAnOrdinaryInline(): void
    {
        $box = $this->find(
            $this->tree(
                '<p><span>a<b class="oof">x</b>b</span></p>',
                '.oof { display: block; position: absolute; }',
            ),
            'span',
        );
        self::assertInstanceOf(AnonymousBlockBox::class, $box);
    }

    public function testFloatedBlockStillSplitsAnOrdinaryInline(): void
    {
        $box = $this->find(
            $this->tree(
                '<p><span>a<b class="blk">x</b>b</span></p>',
                '.blk { display: block; float: left; }',
            ),
            'span',
        );
        self::assertInstanceOf(AnonymousBlockBox::class, $box);
    }
}
