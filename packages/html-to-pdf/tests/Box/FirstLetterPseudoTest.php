<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Sheet\Stylesheet;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Box\TextBox;
use PHPUnit\Framework\TestCase;

/**
 * CSS 2.1 §5.12.2 / CSS Pseudo 4 §4.1 — `::first-letter` box generation.
 *
 * The pseudo is materialised as an inline box wrapped around the first
 * typographic letter unit and the punctuation either side of it, which is
 * exactly the structure the CSS 2.1 reference files hand-write with a
 * `<span>`. These tests pin that structure — the box, the characters it
 * captures, and (mostly) the cases where no box may be generated at all,
 * since every one of those guards exists to stop the pseudo styling the
 * wrong run of text.
 */
final class FirstLetterPseudoTest extends TestCase
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

    private function uaSheet(): Stylesheet
    {
        return $this->css->parseStylesheet(<<<'CSS'
            html, body, div, p { display: block; }
            span, a, em { display: inline; }
            img { display: inline-block; }
        CSS, Origin::UserAgent);
    }

    /**
     * Build the box tree for `$bodyHtml` with `$authorCss` applied, and
     * return the flattened inline leaf sequence of the first `<div>`:
     * each entry is `[pseudoElementOrNull, text]`.
     *
     * @return list<array{0: ?string, 1: string}>
     */
    private function inlineRuns(string $bodyHtml, string $authorCss): array
    {
        $doc = $this->html->parseDocument('<html><body>' . $bodyHtml . '</body></html>');
        $sheets = [$this->uaSheet(), $this->css->parseStylesheet($authorCss, Origin::Author)];
        $root = $this->generator->generate($doc, $sheets);
        self::assertNotNull($root, 'box tree generated');
        $div = $this->findFirstByTag($root, 'div');
        self::assertNotNull($div, 'the <div> generated a box');

        $runs = [];
        $this->collectText($div, null, $runs);
        return $runs;
    }

    /**
     * @param list<array{0: ?string, 1: string}> $runs
     * @param-out list<array{0: ?string, 1: string}> $runs
     */
    private function collectText(Box $box, ?string $pseudo, array &$runs): void
    {
        $pseudo = $box->pseudoElement ?? $pseudo;
        if ($box instanceof TextBox) {
            $runs[] = [$pseudo, $box->text];
            return;
        }
        foreach ($box->children as $child) {
            $this->collectText($child, $pseudo, $runs);
        }
    }

    private function findFirstByTag(Box $root, string $tag): ?Box
    {
        if ($root->element !== null && strtolower($root->element->localName) === $tag) {
            return $root;
        }
        foreach ($root->children as $child) {
            $hit = $this->findFirstByTag($child, $tag);
            if ($hit !== null) {
                return $hit;
            }
        }
        return null;
    }

    // ---------------------------------------------------------------
    // Guards: cases that must NOT generate a `::first-letter` box.
    // ---------------------------------------------------------------

    public function testNoFirstLetterRuleLeavesTextIntact(): void
    {
        $runs = $this->inlineRuns('<div>Test</div>', 'div { color: black; }');
        self::assertSame(
            [[null, 'Test']],
            $runs,
            'with no ::first-letter rule in the document the text node must not be split',
        );
    }

    public function testFirstLetterRuleOnAnotherElementDoesNotSplit(): void
    {
        // The document-wide gate is open (a ::first-letter rule exists),
        // but nothing targets this element, so its cascade for the pseudo
        // is pure inheritance and must produce no box.
        $runs = $this->inlineRuns('<div>Test</div>', 'p::first-letter { color: green; }');
        self::assertSame(
            [[null, 'Test']],
            $runs,
            '::first-letter on a non-matching selector must not split the text',
        );
    }

    public function testFirstLetterDoesNotApplyToNonBlockContainer(): void
    {
        // CSS Pseudo 4 §4.1 — the pseudo applies to block containers only.
        $runs = $this->inlineRuns(
            '<div><span>Test</span></div>',
            'span::first-letter { color: green; }',
        );
        self::assertSame(
            [[null, 'Test']],
            $runs,
            '::first-letter on a `display: inline` element generates nothing',
        );
    }

    public function testPunctuationWithNoLetterGeneratesNothing(): void
    {
        // The pseudo would have to span into the next box to find a
        // letter; rather than style the wrong run, generate nothing.
        $runs = $this->inlineRuns(
            '<div>((<span>Test</span></div>',
            'div::first-letter { color: green; }',
        );
        self::assertSame(
            [[null, '(('], [null, 'Test']],
            $runs,
            'a punctuation-only leading text node must abandon the search, '
            . 'not hand the first letter to the following span',
        );
    }

    public function testAtomicInlineTakesTheFirstLetterSlot(): void
    {
        // An `inline-block` / replaced box occupies the first position, so
        // no character of the following text is the element's first letter.
        $runs = $this->inlineRuns(
            '<div><img/>Test</div>',
            'div::first-letter { color: green; }',
        );
        self::assertSame(
            [[null, 'Test']],
            $runs,
            'text after an atomic inline must not be split',
        );
    }

    // ---------------------------------------------------------------
    // The positive cases.
    // ---------------------------------------------------------------

    public function testSplitsOffTheFirstLetter(): void
    {
        $runs = $this->inlineRuns('<div>Test</div>', 'div::first-letter { color: green; }');
        self::assertSame(
            [['first-letter', 'T'], [null, 'est']],
            $runs,
            'the first letter moves into its own ::first-letter box',
        );
    }

    public function testSingleColonLegacySyntaxAlsoMatches(): void
    {
        // CSS 2.1 spelled every pseudo-element with one colon; the whole
        // CSS 2.1 selector test suite is written that way.
        $runs = $this->inlineRuns('<div>Test</div>', 'div:first-letter { color: green; }');
        self::assertSame(
            [['first-letter', 'T'], [null, 'est']],
            $runs,
            'the legacy one-colon spelling must generate the pseudo too',
        );
    }

    /**
     * CSS 2.1 §5.12.2 — punctuation in the Ps / Pe / Pi / Pf / Po classes
     * on EITHER side of the first letter joins the pseudo-element. One
     * representative codepoint per class, matching the shape of the
     * `first-letter-punctuation-*` reference files.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function punctuationProvider(): iterable
    {
        yield 'Ps left parenthesis' => ["\u{0028}", "\u{0028}T\u{0028}"];
        yield 'Pe right parenthesis' => ["\u{0029}", "\u{0029}T\u{0029}"];
        yield 'Pi left double quote' => ["\u{201C}", "\u{201C}T\u{201C}"];
        yield 'Pf right double quote' => ["\u{201D}", "\u{201D}T\u{201D}"];
        yield 'Po exclamation mark' => ['!', '!T!'];
        yield 'Po Tibetan mark' => ["\u{0F3C}", "\u{0F3C}T\u{0F3C}"];
        yield 'Pd em dash' => ["\u{2014}", "\u{2014}T\u{2014}"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('punctuationProvider')]
    public function testPunctuationEitherSideJoinsTheFirstLetter(string $punct, string $expected): void
    {
        $runs = $this->inlineRuns(
            '<div>' . $punct . 'T' . $punct . 'est</div>',
            'div::first-letter { color: green; }',
        );
        self::assertSame(
            [['first-letter', $expected], [null, 'est']],
            $runs,
            'punctuation before and after the first letter is part of ::first-letter',
        );
    }

    public function testNonPunctuationAfterTheLetterIsNotIncluded(): void
    {
        // The complement of the case above: a letter following the first
        // letter must stay outside, or the pseudo would swallow the word.
        $runs = $this->inlineRuns('<div>(Test</div>', 'div::first-letter { color: green; }');
        self::assertSame(
            [['first-letter', '(T'], [null, 'est']],
            $runs,
            'only punctuation joins the first letter — "e" must stay behind',
        );
    }

    public function testCombiningMarksClusterOntoTheFirstLetter(): void
    {
        // A typographic letter unit is a grapheme cluster: base + marks.
        $runs = $this->inlineRuns(
            "<div>e\u{0301}st</div>",
            'div::first-letter { color: green; }',
        );
        self::assertSame(
            [['first-letter', "e\u{0301}"], [null, 'st']],
            $runs,
            'a combining acute accent belongs to the first letter, not the remainder',
        );
    }

    public function testLeadingWhiteSpaceStaysOutsideTheFirstLetter(): void
    {
        $runs = $this->inlineRuns(
            "<div>\n  Test</div>",
            'div::first-letter { color: green; }',
        );
        self::assertSame(
            [[null, "\n  "], ['first-letter', 'T'], [null, 'est']],
            $runs,
            'collapsible leading white space keeps the host style',
        );
    }

    public function testDescendsIntoALeadingInlineBox(): void
    {
        $runs = $this->inlineRuns(
            '<div><span>Test</span></div>',
            'div::first-letter { color: green; }',
        );
        self::assertSame(
            [['first-letter', 'T'], [null, 'est']],
            $runs,
            'the first letter inside a nested inline still belongs to the block',
        );
    }

    public function testOnlyTheFirstLetterOfTheBlockIsWrapped(): void
    {
        $runs = $this->inlineRuns(
            '<div>Test <span>again</span></div>',
            'div::first-letter { color: green; }',
        );
        self::assertSame(
            [['first-letter', 'T'], [null, 'est '], [null, 'again']],
            $runs,
            'the search latches after the first split',
        );
    }

    public function testFirstLetterCascadeOverridesTheHostStyle(): void
    {
        $doc = $this->html->parseDocument('<html><body><div>Test</div></body></html>');
        $sheets = [
            $this->uaSheet(),
            $this->css->parseStylesheet(
                'div { color: black; } div::first-letter { color: green; font-size: 36px; }',
                Origin::Author,
            ),
        ];
        $root = $this->generator->generate($doc, $sheets);
        self::assertNotNull($root);
        $div = $this->findFirstByTag($root, 'div');
        self::assertNotNull($div);
        $letter = $div->children[0];
        self::assertSame('first-letter', $letter->pseudoElement, 'first child is the pseudo box');
        self::assertSame(
            '#008000',
            (string) $letter->style->get('color')?->toCss(),
            'the pseudo carries its own cascaded colour, not the host black',
        );
        self::assertSame(
            'inline',
            (string) $letter->style->get('display')?->toCss(),
            'CSS Pseudo 4 §4.1 — a non-floated ::first-letter is inline',
        );
    }
}
