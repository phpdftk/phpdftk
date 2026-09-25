<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Box\TextBox;
use PHPUnit\Framework\TestCase;

/**
 * HTML §15.3.13 — "the option element is expected to be rendered by
 * displaying the element's label", where §4.10.10 defines that label as
 * the `label` attribute when present and NON-EMPTY, else the element's
 * text.
 *
 * The two easy things to get wrong both have tests here: treating a
 * present-but-empty `label` as authoritative (which blanks an option
 * that should show its text), and reading the whole subtree instead of
 * the direct child text nodes.
 */
final class OptionLabelTest extends TestCase
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

    /** The text the `<select>` renders, as one string. */
    private function renderedSelectText(string $markup): string
    {
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; } select { display: inline-block; } option { display: none; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate(
            $this->html->parseDocument('<!doctype html><body>' . $markup),
            [$sheet],
        );
        self::assertNotNull($root);
        $out = '';
        $walk = static function (Box $box) use (&$walk, &$out): void {
            if ($box instanceof TextBox
                && $box->element !== null
                && strtolower($box->element->localName) === 'select'
            ) {
                $out .= $box->text;
            }
            foreach ($box->children as $child) {
                $walk($child);
            }
        };
        $walk($root);
        return $out;
    }

    public function testLabelAttributeWinsOverTheElementText(): void
    {
        self::assertSame(
            'Label Text',
            $this->renderedSelectText('<select><option label="Label Text">Element Text</option></select>'),
        );
    }

    public function testLabelAttributeRendersWhenThereIsNoText(): void
    {
        self::assertSame(
            'Label Text',
            $this->renderedSelectText('<select><option label="Label Text"></option></select>'),
        );
    }

    public function testEmptyLabelAttributeFallsBackToTheElementText(): void
    {
        // `label=""` is present but empty, so it is NOT the label — the
        // option still shows its text. Treating "present" as sufficient
        // blanks the option.
        self::assertSame(
            'Fallback',
            $this->renderedSelectText('<select><option label="">Fallback</option></select>'),
        );
    }

    public function testWhitespaceOnlyLabelIsUsedVerbatim(): void
    {
        // A whitespace-only label is not the empty string, so it wins
        // over the text and collapses to blank at paint time.
        self::assertSame(
            '  ',
            $this->renderedSelectText('<select><option label="  ">Not shown</option></select>'),
        );
    }

    public function testElementTextIsStrippedAndCollapsed(): void
    {
        self::assertSame(
            'Element Text',
            $this->renderedSelectText("<select>\n  <option>\n  Element   Text\n  </option>\n</select>"),
        );
    }

    public function testOnlyDirectChildTextNodesCount(): void
    {
        // "Child text content", not the subtree: `a<br>b` is the single
        // option "ab", which is why it must not split across two lines.
        self::assertSame(
            'ab',
            $this->renderedSelectText('<select><option>a<br>b</option></select>'),
        );
    }

    public function testNestedElementTextIsNotPartOfTheLabel(): void
    {
        self::assertSame(
            'outer',
            $this->renderedSelectText('<select><option>outer<span>inner</span></option></select>'),
        );
    }

    public function testOptionWithNeitherLabelNorTextRendersNothing(): void
    {
        self::assertSame(
            '',
            $this->renderedSelectText('<select><option></option></select>'),
        );
    }

    public function testOptgroupLabelStillPrefixesTheOptionLabel(): void
    {
        self::assertSame(
            'Group: Label Text',
            $this->renderedSelectText(
                '<select><optgroup label="Group"><option label="Label Text">Element Text</option></optgroup></select>',
            ),
        );
    }
}
