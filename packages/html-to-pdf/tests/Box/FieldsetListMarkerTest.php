<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * HTML Standard §15.3.9, "The fieldset and legend elements": a
 * `<fieldset>` renders its contents through an anonymous content box, and
 * the element itself "is expected to not generate a `::marker`
 * pseudo-element" — so `display: list-item` on a fieldset produces no
 * bullet and no number, whatever `list-style-type` says.
 *
 * The suppression is the FIELDSET's alone. Its children, including the
 * `<legend>`, are ordinary list items when the author makes them so, and
 * they keep counting in the enclosing list.
 */
final class FieldsetListMarkerTest extends TestCase
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

    private function build(string $bodyHtml, string $authorCss): Box
    {
        $sheets = [
            $this->css->parseStylesheet(
                (new RendererOptions())->effectiveUserAgentStylesheet(),
                Origin::UserAgent,
            ),
            $this->css->parseStylesheet($authorCss, Origin::Author),
        ];
        $doc = $this->html->parseDocument('<!doctype html><html><body>' . $bodyHtml . '</body></html>');
        $root = $this->generator->generate($doc, $sheets);
        self::assertNotNull($root);
        return $root;
    }

    private function findByTag(Box $root, string $tag): ?Box
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
            foreach ($node->children as $child) {
                $stack[] = $child;
            }
        }
        return null;
    }

    public function testFieldsetGeneratesNoMarkerEvenAsAListItem(): void
    {
        $root = $this->build(
            '<fieldset><legend>X</legend></fieldset>',
            'fieldset { display: list-item; }',
        );
        $fieldset = $this->findByTag($root, 'fieldset');
        self::assertNotNull($fieldset);
        $display = $fieldset->style->get('display');
        self::assertInstanceOf(Keyword::class, $display);
        self::assertSame(
            'list-item',
            strtolower($display->name),
            'the author display must survive — only the marker is suppressed',
        );
        self::assertTrue(
            $fieldset->suppressesListMarker,
            'a fieldset must not generate a ::marker',
        );
    }

    public function testAnExplicitListStyleTypeDoesNotReviveTheFieldsetMarker(): void
    {
        // Suppression is unconditional, not a `list-style-type: none`
        // default an author can override.
        $root = $this->build(
            '<fieldset><legend>X</legend></fieldset>',
            'fieldset { display: list-item; list-style-type: decimal; }',
        );
        $fieldset = $this->findByTag($root, 'fieldset');
        self::assertNotNull($fieldset);
        self::assertTrue($fieldset->suppressesListMarker);
    }

    public function testAnInsideMarkerIsNotMaterialisedAsFieldsetContent(): void
    {
        // The `inside` marker is a TEXT child, so suppression has to reach
        // box generation — checking only the painter would leave a stray
        // "1." glyph inside the fieldset.
        $root = $this->build(
            '<fieldset></fieldset>',
            'fieldset { display: list-item; list-style-position: inside; list-style-type: decimal; }',
        );
        $fieldset = $this->findByTag($root, 'fieldset');
        self::assertNotNull($fieldset);
        $text = '';
        $stack = [$fieldset];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TextBox) {
                $text .= $node->text;
            }
            foreach ($node->children as $child) {
                $stack[] = $child;
            }
        }
        self::assertStringNotContainsString('1.', $text, 'no marker text inside the fieldset');
    }

    public function testOtherElementsStillGenerateTheirMarker(): void
    {
        $root = $this->build(
            '<div id=d>x</div>',
            'div { display: list-item; }',
        );
        $div = $this->findByTag($root, 'div');
        self::assertNotNull($div);
        self::assertFalse($div->suppressesListMarker);
    }

    public function testLegendInsideAFieldsetKeepsItsOwnMarker(): void
    {
        $root = $this->build(
            '<fieldset><legend>B</legend></fieldset>',
            'fieldset > * { display: list-item; }',
        );
        $legend = $this->findByTag($root, 'legend');
        self::assertNotNull($legend);
        self::assertFalse(
            $legend->suppressesListMarker,
            'only the fieldset itself is marker-less',
        );
    }
}
