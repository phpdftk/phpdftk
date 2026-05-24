<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Parser;
use Phpdftk\Css\Sheet\AtRule;
use Phpdftk\Css\Sheet\Declaration;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Sheet\StyleRule;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\ValueList;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testEmptyStylesheet(): void
    {
        $sheet = $this->parser->parseStylesheet('');
        self::assertSame([], $sheet->rules);
        self::assertSame(Origin::Author, $sheet->origin);
    }

    public function testOriginIsCarried(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red; }', Origin::UserAgent);
        self::assertSame(Origin::UserAgent, $sheet->origin);
    }

    public function testSingleStyleRule(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red; }');
        self::assertCount(1, $sheet->rules);
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertSame('p', $rule->selectors->text);
        self::assertCount(1, $rule->declarations);
        self::assertSame('color', $rule->declarations[0]->property);
        self::assertInstanceOf(Color::class, $rule->declarations[0]->value);
    }

    public function testMultipleDeclarations(): void
    {
        $sheet = $this->parser->parseStylesheet('div { color: blue; font-size: 16px; margin: 1em 2em; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertCount(3, $rule->declarations);

        self::assertSame('color', $rule->declarations[0]->property);
        self::assertInstanceOf(Color::class, $rule->declarations[0]->value);

        self::assertSame('font-size', $rule->declarations[1]->property);
        self::assertInstanceOf(Length::class, $rule->declarations[1]->value);
        self::assertSame(16.0, $rule->declarations[1]->value->value);

        self::assertSame('margin', $rule->declarations[2]->property);
        self::assertInstanceOf(ValueList::class, $rule->declarations[2]->value);
        self::assertCount(2, $rule->declarations[2]->value->values);
    }

    public function testPropertyNameIsLowercased(): void
    {
        $sheet = $this->parser->parseStylesheet('p { Color: red; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertSame('color', $rule->declarations[0]->property);
    }

    public function testImportantFlag(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red !important; font-size: 16px; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertTrue($rule->declarations[0]->important);
        self::assertFalse($rule->declarations[1]->important);
    }

    public function testImportantCaseInsensitive(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red !IMPORTANT; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertTrue($rule->declarations[0]->important);
    }

    public function testMultipleStyleRules(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red; } div { color: blue; }');
        self::assertCount(2, $sheet->rules);
        self::assertInstanceOf(StyleRule::class, $sheet->rules[0]);
        self::assertInstanceOf(StyleRule::class, $sheet->rules[1]);
        self::assertSame('p', $sheet->rules[0]->selectors->text);
        self::assertSame('div', $sheet->rules[1]->selectors->text);
    }

    public function testSelectorWithMultipleSelectorsViaComma(): void
    {
        $sheet = $this->parser->parseStylesheet('h1, h2, h3 { font-weight: bold; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertSame('h1, h2, h3', $rule->selectors->text);
    }

    public function testSelectorWithCombinators(): void
    {
        $sheet = $this->parser->parseStylesheet('article > p.intro:hover { color: red; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertSame('article > p.intro:hover', $rule->selectors->text);
    }

    public function testSelectorListIsParsedIntoAst(): void
    {
        // Phase 1D.1 wires SelectorParser into the qualified-rule path; verify
        // the resulting StyleRule carries the parsed selectors, not just text.
        $sheet = $this->parser->parseStylesheet('div.foo, #bar > p { color: red; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertCount(2, $rule->selectors->selectors);

        $first = $rule->selectors->selectors[0];
        self::assertCount(1, $first->compounds);
        self::assertCount(2, $first->compounds[0]->compound->components);

        $second = $rule->selectors->selectors[1];
        self::assertCount(2, $second->compounds);
    }

    public function testCommentsAreStripped(): void
    {
        $sheet = $this->parser->parseStylesheet('p { /* between */ color: red; } /* between rules */ div { color: blue; }');
        self::assertCount(2, $sheet->rules);
    }

    public function testAtRuleWithSemicolon(): void
    {
        $sheet = $this->parser->parseStylesheet('@import "foo.css";');
        self::assertCount(1, $sheet->rules);
        $rule = $sheet->rules[0];
        self::assertInstanceOf(AtRule::class, $rule);
        self::assertSame('import', $rule->name);
        self::assertNull($rule->block);
        self::assertStringContainsString('"foo.css"', $rule->prelude);
    }

    public function testAtRuleCharset(): void
    {
        $sheet = $this->parser->parseStylesheet('@charset "utf-8";');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(AtRule::class, $rule);
        self::assertSame('charset', $rule->name);
    }

    public function testFontFaceBlock(): void
    {
        $css = '@font-face { font-family: "Inter"; src: url(inter.woff2); font-weight: 400; }';
        $sheet = $this->parser->parseStylesheet($css);
        $rule = $sheet->rules[0];
        self::assertInstanceOf(AtRule::class, $rule);
        self::assertSame('font-face', $rule->name);
        self::assertNotNull($rule->block);
        // @font-face's block parses as declarations.
        self::assertCount(3, $rule->block->contents);
        foreach ($rule->block->contents as $item) {
            self::assertInstanceOf(Declaration::class, $item);
        }
    }

    public function testMediaQueryBlockContainsStyleRules(): void
    {
        $css = '@media (max-width: 600px) { p { color: red; } h1 { font-size: 2em; } }';
        $sheet = $this->parser->parseStylesheet($css);
        $rule = $sheet->rules[0];
        self::assertInstanceOf(AtRule::class, $rule);
        self::assertSame('media', $rule->name);
        self::assertStringContainsString('max-width', $rule->prelude);
        self::assertNotNull($rule->block);
        // @media's block parses as nested rules.
        self::assertCount(2, $rule->block->contents);
        foreach ($rule->block->contents as $item) {
            self::assertInstanceOf(StyleRule::class, $item);
        }
    }

    public function testPageRule(): void
    {
        $css = '@page :first { margin-top: 2cm; }';
        $sheet = $this->parser->parseStylesheet($css);
        $rule = $sheet->rules[0];
        self::assertInstanceOf(AtRule::class, $rule);
        self::assertSame('page', $rule->name);
        self::assertNotNull($rule->block);
        // @page parses contents as declarations.
        self::assertCount(1, $rule->block->contents);
        self::assertInstanceOf(Declaration::class, $rule->block->contents[0]);
    }

    public function testPageRuleWithMarginBoxes(): void
    {
        // CSS Paged Media 3 §3: `@page` may contain both declarations
        // (the page box's own properties) AND nested at-rules (the
        // margin-box at-rules `@top-center`, etc.). The parser must
        // emit both kinds in the same block.
        $css = '@page { margin: 1in; @top-center { content: "Title"; } '
            . '@bottom-right { content: "Page"; } }';
        $sheet = $this->parser->parseStylesheet($css);
        $rule = $sheet->rules[0];
        self::assertInstanceOf(AtRule::class, $rule);
        self::assertNotNull($rule->block);
        $contents = $rule->block->contents;
        self::assertCount(3, $contents);
        self::assertInstanceOf(Declaration::class, $contents[0]);
        self::assertSame('margin', $contents[0]->property);
        self::assertInstanceOf(AtRule::class, $contents[1]);
        self::assertSame('top-center', $contents[1]->name);
        self::assertInstanceOf(AtRule::class, $contents[2]);
        self::assertSame('bottom-right', $contents[2]->name);
        // Margin-box at-rules contain declarations — verify the inner
        // `content` declaration was actually parsed (not silently dropped
        // as the rule-list branch would do).
        $topInner = $contents[1]->block?->contents ?? [];
        self::assertCount(1, $topInner);
        self::assertInstanceOf(Declaration::class, $topInner[0]);
        self::assertSame('content', $topInner[0]->property);
    }

    public function testCustomPropertyDeclaration(): void
    {
        $sheet = $this->parser->parseStylesheet(':root { --brand: #ff8000; }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertSame('--brand', $rule->declarations[0]->property);
        self::assertInstanceOf(Color::class, $rule->declarations[0]->value);
    }

    public function testParseInlineStyle(): void
    {
        $rule = $this->parser->parseInlineStyle('color: red; font-size: 16px');
        self::assertSame('', $rule->selectors->text);
        self::assertCount(2, $rule->declarations);
        self::assertSame('color', $rule->declarations[0]->property);
        self::assertSame('font-size', $rule->declarations[1]->property);
    }

    public function testParseValueHelper(): void
    {
        $v = $this->parser->parseValue('red');
        self::assertInstanceOf(Color::class, $v);
    }

    public function testStylesheetWithCdoCdc(): void
    {
        // Legacy script-inline compat: top-level <!-- and --> should be skipped.
        $sheet = $this->parser->parseStylesheet('<!-- p { color: red; } -->');
        self::assertCount(1, $sheet->rules);
        self::assertInstanceOf(StyleRule::class, $sheet->rules[0]);
    }

    public function testDeclarationWithComplexValue(): void
    {
        $sheet = $this->parser->parseStylesheet('div { background: linear-gradient(red, blue); }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertCount(1, $rule->declarations);
        // Value is the LinearGradient itself.
        self::assertSame('background', $rule->declarations[0]->property);
    }

    public function testNestedFunctionsInDeclarations(): void
    {
        $sheet = $this->parser->parseStylesheet('div { width: calc(100% - 20px); margin: var(--gap, 1rem); }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertCount(2, $rule->declarations);
        self::assertSame('width', $rule->declarations[0]->property);
        self::assertSame('margin', $rule->declarations[1]->property);
    }

    public function testMissingClosingBraceTerminatesAtEof(): void
    {
        // Recovery: missing } at EOF still produces the rule with what we have.
        $sheet = $this->parser->parseStylesheet('p { color: red');
        self::assertCount(1, $sheet->rules);
    }

    public function testEmptyDeclarationBlock(): void
    {
        $sheet = $this->parser->parseStylesheet('p { }');
        self::assertCount(1, $sheet->rules);
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertSame([], $rule->declarations);
    }

    public function testTrailingSemicolonOptional(): void
    {
        // Last declaration can omit its trailing ;.
        $sheet = $this->parser->parseStylesheet('p { color: red }');
        $rule = $sheet->rules[0];
        self::assertInstanceOf(StyleRule::class, $rule);
        self::assertCount(1, $rule->declarations);
    }

    public function testRealisticStylesheet(): void
    {
        $css = <<<CSS
            /* base styles */
            :root {
              --primary: #007bff;
              --gap: 1rem;
            }

            body {
              font-family: "Inter", sans-serif;
              font-size: 16px;
              line-height: 1.5;
              color: #222;
            }

            @media (max-width: 600px) {
              body { font-size: 14px; }
              h1 { font-size: 1.5rem; }
            }

            .button {
              padding: 0.5em 1em;
              background: var(--primary);
              border-radius: 4px;
            }

            .button:hover {
              background: #0056b3;
            }

            @font-face {
              font-family: "Inter";
              src: url("inter.woff2") format("woff2");
              font-weight: 400;
            }
            CSS;
        $sheet = $this->parser->parseStylesheet($css);
        // 1 (:root) + 1 (body) + 1 (@media) + 1 (.button) + 1 (.button:hover) + 1 (@font-face)
        self::assertCount(6, $sheet->rules);
        self::assertInstanceOf(StyleRule::class, $sheet->rules[0]);
        self::assertSame(':root', $sheet->rules[0]->selectors->text);
        self::assertInstanceOf(AtRule::class, $sheet->rules[2]);
        self::assertSame('media', $sheet->rules[2]->name);
    }
}
