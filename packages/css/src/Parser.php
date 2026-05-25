<?php

declare(strict_types=1);

namespace Phpdftk\Css;

use Phpdftk\Css\Selector\SelectorList;
use Phpdftk\Css\Sheet\AtRule;
use Phpdftk\Css\Sheet\AtRuleBlock;
use Phpdftk\Css\Sheet\Declaration;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Sheet\Rule;
use Phpdftk\Css\Sheet\Stylesheet;
use Phpdftk\Css\Sheet\StyleRule;
use Phpdftk\Css\Token\AtKeywordToken;
use Phpdftk\Css\Token\CdcToken;
use Phpdftk\Css\Token\CdoToken;
use Phpdftk\Css\Token\ColonToken;
use Phpdftk\Css\Token\DelimToken;
use Phpdftk\Css\Token\EofToken;
use Phpdftk\Css\Token\FunctionToken;
use Phpdftk\Css\Token\IdentToken;
use Phpdftk\Css\Token\LeftBraceToken;
use Phpdftk\Css\Token\LeftBracketToken;
use Phpdftk\Css\Token\LeftParenToken;
use Phpdftk\Css\Token\RightBraceToken;
use Phpdftk\Css\Token\RightBracketToken;
use Phpdftk\Css\Token\RightParenToken;
use Phpdftk\Css\Token\SemicolonToken;
use Phpdftk\Css\Token\Token;
use Phpdftk\Css\Token\WhitespaceToken;
use Phpdftk\Css\Value\Value;

/**
 * Stylesheet-level parser per CSS Syntax Module 3 §5 ("Parsing").
 *
 * Tokenizes the input, then runs the spec's "consume a list of rules" /
 * "consume an at-rule" / "consume a qualified rule" / "consume a list of
 * declarations" sub-algorithms. Output is a typed {@see Stylesheet} tree.
 *
 * Value parsing inside declarations delegates to {@see ValueParser}.
 * Selector parsing is deferred to Phase 1D — for now {@see StyleRule}'s
 * `SelectorList` carries the raw selector text.
 */
final class Parser
{
    private readonly ValueParser $valueParser;

    public function __construct(?ValueParser $valueParser = null)
    {
        $this->valueParser = $valueParser ?? new ValueParser();
    }

    public function parseStylesheet(string $css, Origin $origin = Origin::Author): Stylesheet
    {
        $tokens = (new Tokenizer($css))->tokenize();
        return new Stylesheet($this->consumeListOfRules($tokens, topLevel: true), $origin);
    }

    /**
     * Parse an HTML `style="…"` attribute (or any free-form declaration list)
     * into a StyleRule with an empty selector.
     */
    public function parseInlineStyle(string $css): StyleRule
    {
        $tokens = (new Tokenizer($css))->tokenize();
        return new StyleRule(new SelectorList(''), $this->consumeListOfDeclarations($tokens));
    }

    public function parseValue(string $css, string $propertyHint = ''): Value
    {
        return $this->valueParser->parseFromString($css);
    }

    /**
     * @param list<Token> $tokens
     * @return list<Rule>
     */
    private function consumeListOfRules(array $tokens, bool $topLevel): array
    {
        $rules = [];
        $i = 0;
        $n = count($tokens);
        while ($i < $n) {
            $t = $tokens[$i];
            if ($t instanceof WhitespaceToken) {
                $i++;
                continue;
            }
            if ($t instanceof EofToken) {
                break;
            }
            if (($t instanceof CdoToken || $t instanceof CdcToken) && $topLevel) {
                $i++;
                continue;
            }
            if ($t instanceof AtKeywordToken) {
                $rules[] = $this->consumeAtRule($tokens, $i);
                continue;
            }
            // Qualified rule (style rule).
            $rule = $this->consumeQualifiedRule($tokens, $i);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }
        return $rules;
    }

    /**
     * Consume an at-rule. Advances $i past the rule.
     *
     * @param list<Token> $tokens
     */
    private function consumeAtRule(array $tokens, int &$i): AtRule
    {
        $name = $tokens[$i] instanceof AtKeywordToken ? $tokens[$i]->value : '';
        $i++;
        $prelude = [];
        $depth = 0;
        $n = count($tokens);
        while ($i < $n) {
            $t = $tokens[$i];
            if ($depth === 0 && $t instanceof SemicolonToken) {
                $i++;
                return new AtRule($name, self::serializePrelude($prelude), null);
            }
            if ($t instanceof EofToken) {
                return new AtRule($name, self::serializePrelude($prelude), null);
            }
            if ($depth === 0 && $t instanceof LeftBraceToken) {
                $i++;
                $blockTokens = $this->consumeBlock($tokens, $i);
                $block = new AtRuleBlock($this->parseAtRuleBlockContents($name, $blockTokens));
                return new AtRule($name, self::serializePrelude($prelude), $block);
            }
            if ($t instanceof LeftParenToken || $t instanceof LeftBracketToken || $t instanceof FunctionToken) {
                $depth++;
            } elseif ($t instanceof RightParenToken || $t instanceof RightBracketToken) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $prelude[] = $t;
            $i++;
        }
        return new AtRule($name, self::serializePrelude($prelude), null);
    }

    /**
     * Consume a qualified rule (selector + declaration block). Advances $i.
     *
     * @param list<Token> $tokens
     */
    private function consumeQualifiedRule(array $tokens, int &$i): ?StyleRule
    {
        $prelude = [];
        $depth = 0;
        $n = count($tokens);
        while ($i < $n) {
            $t = $tokens[$i];
            if ($t instanceof EofToken) {
                return null; // parse error: missing block
            }
            if ($depth === 0 && $t instanceof LeftBraceToken) {
                $i++;
                $blockTokens = $this->consumeBlock($tokens, $i);
                $preludeText = trim(self::serializePrelude($prelude));
                $selectors = \Phpdftk\Css\Selector\SelectorParser::parseTokens($prelude, $preludeText);
                return new StyleRule(
                    $selectors,
                    $this->consumeListOfDeclarations($blockTokens),
                );
            }
            if ($t instanceof LeftParenToken || $t instanceof LeftBracketToken || $t instanceof FunctionToken) {
                $depth++;
            } elseif ($t instanceof RightParenToken || $t instanceof RightBracketToken) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $prelude[] = $t;
            $i++;
        }
        return null;
    }

    /**
     * Consume the inside of a `{ ... }` block. Assumes $i is just past the
     * opening brace; advances past the closing brace.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function consumeBlock(array $tokens, int &$i): array
    {
        $contents = [];
        $depth = 1;
        $n = count($tokens);
        // Loop terminates from inside on EOF or matching close brace; the
        // depth check at the top is always true here, so we use just $i < $n.
        while ($i < $n) {
            $t = $tokens[$i];
            if ($t instanceof EofToken) {
                break;
            }
            if ($t instanceof LeftBraceToken) {
                $depth++;
                $contents[] = $t;
                $i++;
                continue;
            }
            if ($t instanceof RightBraceToken) {
                $depth--;
                if ($depth === 0) {
                    $i++;
                    break;
                }
                $contents[] = $t;
                $i++;
                continue;
            }
            $contents[] = $t;
            $i++;
        }
        return $contents;
    }

    /**
     * Parse a list of declarations from a block-content token list. Per CSS
     * Syntax 3 §5.4.4. Semicolon-separated; each non-empty section is one
     * declaration (or a parse error to drop).
     *
     * @param list<Token> $tokens
     * @return list<Declaration>
     */
    private function consumeListOfDeclarations(array $tokens): array
    {
        $declarations = [];
        $current = [];
        $depth = 0;
        $i = 0;
        $n = count($tokens);
        while ($i < $n) {
            $t = $tokens[$i];
            if ($depth === 0 && $t instanceof SemicolonToken) {
                $decl = $this->parseDeclarationFromTokens($current);
                if ($decl !== null) {
                    $declarations[] = $decl;
                }
                $current = [];
                $i++;
                continue;
            }
            if ($t instanceof LeftParenToken || $t instanceof LeftBracketToken
                || $t instanceof LeftBraceToken || $t instanceof FunctionToken
            ) {
                $depth++;
            } elseif ($t instanceof RightParenToken || $t instanceof RightBracketToken
                || $t instanceof RightBraceToken
            ) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $current[] = $t;
            $i++;
        }
        $decl = $this->parseDeclarationFromTokens($current);
        if ($decl !== null) {
            $declarations[] = $decl;
        }
        return $declarations;
    }

    /**
     * Turn a "section" of tokens (the part between two `;` boundaries) into
     * a Declaration, or return null if it doesn't shape as one.
     *
     * @param list<Token> $tokens
     */
    private function parseDeclarationFromTokens(array $tokens): ?Declaration
    {
        $tokens = self::trimWhitespace($tokens);
        if ($tokens === []) {
            return null;
        }
        $head = $tokens[0];
        if (!$head instanceof IdentToken) {
            return null;
        }
        $property = strtolower($head->value);
        // Find the colon.
        $colonIdx = null;
        for ($i = 1; $i < count($tokens); $i++) {
            if ($tokens[$i] instanceof ColonToken) {
                $colonIdx = $i;
                break;
            }
            if (!$tokens[$i] instanceof WhitespaceToken) {
                return null; // unexpected token before colon
            }
        }
        if ($colonIdx === null) {
            return null;
        }
        $valueTokens = array_slice($tokens, $colonIdx + 1);
        $valueTokens = self::trimWhitespace($valueTokens);
        // Check for !important suffix.
        $important = false;
        if (count($valueTokens) >= 2) {
            $lastIdx = count($valueTokens) - 1;
            $tail = $valueTokens[$lastIdx];
            $beforeTail = null;
            $bangIdx = null;
            for ($j = $lastIdx; $j >= 0; $j--) {
                $tt = $valueTokens[$j];
                if ($tt instanceof DelimToken && $tt->value === '!') {
                    $bangIdx = $j;
                    break;
                }
                if (!$tt instanceof IdentToken && !$tt instanceof WhitespaceToken) {
                    break;
                }
            }
            if ($bangIdx !== null) {
                // Whatever's after the `!` must be `important` (case-insensitive).
                $after = self::trimWhitespace(array_slice($valueTokens, $bangIdx + 1));
                if (count($after) === 1
                    && $after[0] instanceof IdentToken
                    && strcasecmp($after[0]->value, 'important') === 0
                ) {
                    $important = true;
                    $valueTokens = self::trimWhitespace(array_slice($valueTokens, 0, $bangIdx));
                }
            }
        }
        $value = $this->valueParser->parse($valueTokens);
        // CSS Transforms 2 §6: the `transform` property's value is a
        // list of transform-functions. Post-process the generic
        // `CssFunction`/`ValueList` into a typed `Transform` so the
        // painter can consume it directly without re-parsing.
        if ($property === 'transform') {
            $value = $this->valueParser->postProcessTransform($value);
        }
        return new Declaration($property, $value, $important);
    }

    /**
     * Parse the body of an at-rule block: try each comma-or-semicolon-free
     * section as a declaration first, then as a rule. For declaration-only
     * at-rules (`@font-face`, `@page`, `@property`, `@counter-style`) the
     * decl path wins; for nested-rule at-rules (`@media`, `@supports`,
     * `@keyframes`'s blocks) the rule path wins.
     *
     * @param list<Token> $tokens
     * @return list<Rule|Declaration>
     */
    private function parseAtRuleBlockContents(string $atRuleName, array $tokens): array
    {
        $lcName = strtolower($atRuleName);
        $declOnly = ['font-face', 'property', 'counter-style', 'font-feature-values'];
        // CSS Paged Media 3 §3.6 margin-box at-rules (the 16 positions);
        // each one contains declarations only (`content`, `font-size`,
        // `color`, etc.). Treat them as declaration-only.
        $marginBoxRules = [
            'top-left-corner', 'top-left', 'top-center', 'top-right', 'top-right-corner',
            'right-top', 'right-middle', 'right-bottom',
            'bottom-right-corner', 'bottom-right', 'bottom-center', 'bottom-left', 'bottom-left-corner',
            'left-bottom', 'left-middle', 'left-top',
        ];
        if (in_array($lcName, $declOnly, true) || in_array($lcName, $marginBoxRules, true)) {
            return $this->consumeListOfDeclarations($tokens);
        }
        // CSS Paged Media 3 §3: `@page` blocks can contain BOTH
        // declarations (the page box's own props like margin/size) AND
        // nested at-rules (the 16 margin-box at-rules). Use the
        // mixed-content parser for it.
        if ($lcName === 'page') {
            return $this->consumeDeclarationsAndAtRules($tokens);
        }
        // Otherwise parse as a rule list.
        return $this->consumeListOfRules($tokens, topLevel: false);
    }

    /**
     * Parse a block that may contain either declarations (`prop: value;`)
     * or nested at-rules (`@name { ... }`). Used for `@page` per CSS
     * Paged Media 3 §3 — the page box's own properties live alongside
     * its margin-box at-rules. Section boundaries are `;` (closes a
     * declaration) or a `{...}` block (closes an at-rule).
     *
     * @param list<Token> $tokens
     * @return list<Rule|Declaration>
     */
    private function consumeDeclarationsAndAtRules(array $tokens): array
    {
        $out = [];
        $i = 0;
        $n = count($tokens);
        while ($i < $n) {
            $t = $tokens[$i];
            if ($t instanceof WhitespaceToken || $t instanceof SemicolonToken) {
                $i++;
                continue;
            }
            if ($t instanceof AtKeywordToken) {
                $out[] = $this->consumeAtRule($tokens, $i);
                continue;
            }
            // Collect a declaration: tokens up to next top-level `;` or
            // end of input. Don't break inside braces (a value can carry
            // a function call with parens; we don't expect braces inside
            // a declaration, but stay defensive).
            $start = $i;
            $depth = 0;
            while ($i < $n) {
                $u = $tokens[$i];
                if ($depth === 0 && $u instanceof SemicolonToken) {
                    break;
                }
                if ($u instanceof LeftParenToken || $u instanceof LeftBracketToken
                    || $u instanceof LeftBraceToken || $u instanceof FunctionToken
                ) {
                    $depth++;
                } elseif ($u instanceof RightParenToken || $u instanceof RightBracketToken
                    || $u instanceof RightBraceToken
                ) {
                    if ($depth > 0) {
                        $depth--;
                    }
                }
                $i++;
            }
            $section = array_slice($tokens, $start, $i - $start);
            $decl = $this->parseDeclarationFromTokens($section);
            if ($decl !== null) {
                $out[] = $decl;
            }
            // Skip the `;` if present.
            if ($i < $n && $tokens[$i] instanceof SemicolonToken) {
                $i++;
            }
        }
        return $out;
    }

    /**
     * Render a prelude (or selector) token list back to a normalised string:
     * collapse runs of whitespace, trim ends, preserve the rest verbatim.
     *
     * @param list<Token> $tokens
     */
    private static function serializePrelude(array $tokens): string
    {
        $out = '';
        $lastWasSpace = false;
        foreach ($tokens as $t) {
            $piece = self::tokenToText($t);
            if ($t instanceof WhitespaceToken) {
                if (!$lastWasSpace && $out !== '') {
                    $out .= ' ';
                    $lastWasSpace = true;
                }
                continue;
            }
            $out .= $piece;
            $lastWasSpace = false;
        }
        return trim($out);
    }

    private static function tokenToText(Token $t): string
    {
        return match (true) {
            $t instanceof IdentToken => $t->value,
            $t instanceof AtKeywordToken => '@' . $t->value,
            $t instanceof FunctionToken => $t->name . '(',
            $t instanceof \Phpdftk\Css\Token\HashToken => '#' . $t->value,
            $t instanceof \Phpdftk\Css\Token\StringToken => '"' . str_replace('"', '\\"', $t->value) . '"',
            $t instanceof \Phpdftk\Css\Token\UrlToken => 'url(' . $t->value . ')',
            $t instanceof \Phpdftk\Css\Token\NumberToken => (string) (fmod($t->value, 1.0) === 0.0 ? (int) $t->value : $t->value),
            $t instanceof \Phpdftk\Css\Token\PercentageToken => (string) (fmod($t->value, 1.0) === 0.0 ? (int) $t->value : $t->value) . '%',
            $t instanceof \Phpdftk\Css\Token\DimensionToken => (string) (fmod($t->value, 1.0) === 0.0 ? (int) $t->value : $t->value) . $t->unit,
            $t instanceof DelimToken => $t->value,
            $t instanceof ColonToken => ':',
            $t instanceof SemicolonToken => ';',
            $t instanceof \Phpdftk\Css\Token\CommaToken => ',',
            $t instanceof LeftParenToken => '(',
            $t instanceof RightParenToken => ')',
            $t instanceof LeftBracketToken => '[',
            $t instanceof RightBracketToken => ']',
            $t instanceof LeftBraceToken => '{',
            $t instanceof RightBraceToken => '}',
            $t instanceof WhitespaceToken => ' ',
            $t instanceof CdoToken => '<!--',
            $t instanceof CdcToken => '-->',
            default => '',
        };
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function trimWhitespace(array $tokens): array
    {
        $start = 0;
        $end = count($tokens) - 1;
        while ($start <= $end && ($tokens[$start] instanceof WhitespaceToken || $tokens[$start] instanceof EofToken)) {
            $start++;
        }
        while ($end >= $start && ($tokens[$end] instanceof WhitespaceToken || $tokens[$end] instanceof EofToken)) {
            $end--;
        }
        return array_slice($tokens, $start, $end - $start + 1);
    }
}
