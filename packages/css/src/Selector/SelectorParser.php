<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

use Phpdftk\Css\Token\AtKeywordToken;
use Phpdftk\Css\Token\ColonToken;
use Phpdftk\Css\Token\CommaToken;
use Phpdftk\Css\Token\DelimToken;
use Phpdftk\Css\Token\DimensionToken;
use Phpdftk\Css\Token\EofToken;
use Phpdftk\Css\Token\FunctionToken;
use Phpdftk\Css\Token\HashToken;
use Phpdftk\Css\Token\HashTokenType;
use Phpdftk\Css\Token\IdentToken;
use Phpdftk\Css\Token\LeftBracketToken;
use Phpdftk\Css\Token\NumberToken;
use Phpdftk\Css\Token\NumberTokenType;
use Phpdftk\Css\Token\RightBracketToken;
use Phpdftk\Css\Token\RightParenToken;
use Phpdftk\Css\Token\StringToken;
use Phpdftk\Css\Token\Token;
use Phpdftk\Css\Token\WhitespaceToken;
use Phpdftk\Css\Tokenizer;

/**
 * Selectors-4 parser. Consumes the prelude token list of a style rule (or a
 * raw string for `:is()` / `:where()` / `:not()` / `:has()` argument
 * parsing) and produces a `SelectorList` of `ComplexSelector`s.
 *
 * Parsing follows the grammar in Selectors 4 §17 with the common-path subset
 * needed for print rendering. Specifically supported:
 *  - type, universal, id, class, attribute, pseudo-class, pseudo-element
 *  - all combinators: descendant, `>`, `+`, `~`, `||`
 *  - `:is`, `:not`, `:where`, `:has` argument lists (recursive)
 *  - `:nth-child` family with An+B
 *  - `:lang`, `:dir`, `:host`, `:host-context`, `::slotted`, `::part`,
 *    `::theme` and other functional forms (parsed but match semantics live
 *    in the matcher in Phase 1D.2).
 *
 * The parser is forgiving by default for top-level selector lists and
 * non-forgiving for `:not` / `:has`. `:is` / `:where` are forgiving per spec.
 */
final class SelectorParser
{
    /** @var list<Token> */
    private array $tokens;
    private int $i = 0;
    private int $count;

    /** Parse a selector source string into a SelectorList. */
    public static function parse(string $source): SelectorList
    {
        $tokenizer = new Tokenizer($source);
        $tokens = array_values(array_filter(
            $tokenizer->tokenize(),
            static fn(Token $t): bool => !($t instanceof EofToken),
        ));
        return self::parseTokens($tokens, $source);
    }

    /**
     * Parse a pre-tokenised prelude into a SelectorList. The CSS stylesheet
     * parser calls this with the prelude tokens of a qualified rule.
     *
     * @param list<Token> $tokens
     */
    public static function parseTokens(array $tokens, string $sourceText = ''): SelectorList
    {
        $self = new self($tokens);
        $selectors = $self->parseComplexSelectorList(forgiving: true);
        return new SelectorList($sourceText, $selectors);
    }

    /** @param list<Token> $tokens */
    private function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->count = count($tokens);
    }

    /**
     * `<complex-selector-list> = <complex-selector>#`
     *
     * @return list<ComplexSelector>
     */
    private function parseComplexSelectorList(bool $forgiving): array
    {
        $out = [];
        while (true) {
            $this->skipWhitespace();
            if ($this->eof()) {
                break;
            }
            $start = $this->i;
            try {
                $sel = $this->parseComplexSelector();
                if ($sel !== null) {
                    $out[] = $sel;
                }
            } catch (SelectorSyntaxException) {
                if (!$forgiving) {
                    throw new SelectorSyntaxException('Invalid selector');
                }
                // Skip to next comma or EOF.
                $this->skipToNextSelector();
            }
            $this->skipWhitespace();
            if ($this->eof()) {
                break;
            }
            $next = $this->peek();
            if ($next instanceof CommaToken) {
                $this->i++;
                continue;
            }
            // No comma but tokens remain — recover or fail.
            if ($this->i === $start) {
                // Made no progress; bail out to avoid infinite loop.
                $this->i++;
                if (!$forgiving) {
                    throw new SelectorSyntaxException('Unexpected token in selector list');
                }
            }
        }
        return $out;
    }

    private function parseComplexSelector(): ?ComplexSelector
    {
        $startTok = $this->i;
        $compound = $this->parseCompoundSelector();
        if ($compound === null) {
            return null;
        }
        $parts = [];
        while (true) {
            $combinator = $this->parseCombinator();
            if ($combinator === null) {
                $parts[] = new CompoundSelectorWithCombinator($compound, null);
                break;
            }
            $next = $this->parseCompoundSelector();
            if ($next === null) {
                throw new SelectorSyntaxException('Expected compound selector after combinator');
            }
            $parts[] = new CompoundSelectorWithCombinator($compound, $combinator);
            $compound = $next;
        }
        $text = self::serializeTokenRange($this->tokens, $startTok, $this->i);
        return new ComplexSelector($parts, trim($text));
    }

    private function parseCompoundSelector(): ?CompoundSelector
    {
        $components = [];

        // Optional type/universal selector first.
        $typed = $this->tryParseTypeOrUniversal();
        if ($typed !== null) {
            $components[] = $typed;
        }
        while (true) {
            $sub = $this->tryParseSubclassOrPseudo();
            if ($sub === null) {
                break;
            }
            $components[] = $sub;
        }
        if ($components === []) {
            return null;
        }
        return new CompoundSelector($components);
    }

    private function tryParseTypeOrUniversal(): ?SimpleSelector
    {
        $save = $this->i;
        $prefix = null;

        // Look for ns-prefix: ident|, *|, or |.
        if ($this->peek() instanceof IdentToken
            && $this->peekAt(1) instanceof DelimToken
            && $this->peekAt(1)->value === '|'
            && !($this->peekAt(2) instanceof DelimToken && $this->peekAt(2)->value === '=')
        ) {
            $prefix = $this->peek()->value;
            $this->i += 2;
        } elseif ($this->peek() instanceof DelimToken
            && $this->peek()->value === '*'
            && $this->peekAt(1) instanceof DelimToken
            && $this->peekAt(1)->value === '|'
        ) {
            $prefix = '*';
            $this->i += 2;
        } elseif ($this->peek() instanceof DelimToken
            && $this->peek()->value === '|'
            && !($this->peekAt(1) instanceof DelimToken && $this->peekAt(1)->value === '|')
        ) {
            $prefix = '';
            $this->i++;
        }

        $tok = $this->peek();
        if ($tok instanceof IdentToken) {
            $this->i++;
            return new TypeSelector($tok->value, $prefix);
        }
        if ($tok instanceof DelimToken && $tok->value === '*') {
            $this->i++;
            return new UniversalSelector($prefix);
        }
        // Not a type selector — rewind.
        $this->i = $save;
        return null;
    }

    private function tryParseSubclassOrPseudo(): ?SimpleSelector
    {
        $tok = $this->peek();
        if ($tok instanceof HashToken && $tok->type === HashTokenType::Id) {
            $this->i++;
            return new IdSelector($tok->value);
        }
        if ($tok instanceof DelimToken && $tok->value === '.') {
            if ($this->peekAt(1) instanceof IdentToken) {
                $this->i += 2;
                /** @var IdentToken $ident */
                $ident = $this->tokens[$this->i - 1];
                return new ClassSelector($ident->value);
            }
            return null;
        }
        if ($tok instanceof LeftBracketToken) {
            return $this->parseAttributeSelector();
        }
        if ($tok instanceof ColonToken) {
            return $this->parsePseudoSelector();
        }
        return null;
    }

    private function parseAttributeSelector(): AttributeSelector
    {
        // Caller verified the `[`.
        $this->i++;
        $this->skipWhitespace();
        $prefix = null;
        // Optional namespace prefix. A `|` followed by `=` is the |= match
        // operator, not a namespace separator.
        if ($this->peek() instanceof IdentToken
            && $this->peekAt(1) instanceof DelimToken
            && $this->peekAt(1)->value === '|'
            && !($this->peekAt(2) instanceof DelimToken && $this->peekAt(2)->value === '=')
        ) {
            $prefix = $this->peek()->value;
            $this->i += 2;
        } elseif ($this->peek() instanceof DelimToken && $this->peek()->value === '*'
            && $this->peekAt(1) instanceof DelimToken && $this->peekAt(1)->value === '|'
            && !($this->peekAt(2) instanceof DelimToken && $this->peekAt(2)->value === '=')
        ) {
            $prefix = '*';
            $this->i += 2;
        } elseif ($this->peek() instanceof DelimToken && $this->peek()->value === '|'
            && !($this->peekAt(1) instanceof DelimToken && in_array($this->peekAt(1)->value, ['|', '='], true))
        ) {
            $prefix = '';
            $this->i++;
        }

        $nameTok = $this->peek();
        if (!$nameTok instanceof IdentToken) {
            throw new SelectorSyntaxException('Expected attribute name');
        }
        $name = $nameTok->value;
        $this->i++;
        $this->skipWhitespace();

        // `]` → existence only.
        if ($this->peek() instanceof RightBracketToken) {
            $this->i++;
            return new AttributeSelector($name, AttributeMatchType::Exists, namespacePrefix: $prefix);
        }
        $matcher = $this->parseAttrMatcher();
        $this->skipWhitespace();
        $valueTok = $this->peek();
        $value = null;
        if ($valueTok instanceof StringToken) {
            $value = $valueTok->value;
            $this->i++;
        } elseif ($valueTok instanceof IdentToken) {
            $value = $valueTok->value;
            $this->i++;
        } else {
            throw new SelectorSyntaxException('Expected attribute value');
        }
        $this->skipWhitespace();
        $ci = false;
        $modifier = $this->peek();
        if ($modifier instanceof IdentToken) {
            $lower = strtolower($modifier->value);
            if ($lower === 'i') {
                $ci = true;
                $this->i++;
                $this->skipWhitespace();
            } elseif ($lower === 's') {
                $ci = false;
                $this->i++;
                $this->skipWhitespace();
            }
        }
        if (!($this->peek() instanceof RightBracketToken)) {
            throw new SelectorSyntaxException('Expected `]`');
        }
        $this->i++;
        return new AttributeSelector($name, $matcher, $value, $prefix, $ci);
    }

    private function parseAttrMatcher(): AttributeMatchType
    {
        $tok = $this->peek();
        if ($tok instanceof DelimToken && $tok->value === '=') {
            $this->i++;
            return AttributeMatchType::Equals;
        }
        if ($tok instanceof DelimToken && in_array($tok->value, ['~', '|', '^', '$', '*'], true)) {
            $next = $this->peekAt(1);
            if ($next instanceof DelimToken && $next->value === '=') {
                $this->i += 2;
                return match ($tok->value) {
                    '~' => AttributeMatchType::Includes,
                    '|' => AttributeMatchType::DashMatch,
                    '^' => AttributeMatchType::PrefixMatch,
                    '$' => AttributeMatchType::SuffixMatch,
                    '*' => AttributeMatchType::SubstringMatch,
                };
            }
        }
        throw new SelectorSyntaxException('Expected attribute matcher operator');
    }

    private function parsePseudoSelector(): SimpleSelector
    {
        $this->i++; // consume `:`
        $isPseudoElement = false;
        if ($this->peek() instanceof ColonToken) {
            $isPseudoElement = true;
            $this->i++;
        }
        $next = $this->peek();
        if ($next instanceof IdentToken) {
            $this->i++;
            $name = strtolower($next->value);
            // Per CSS 2.1 legacy: ::before/::after/::first-line/::first-letter
            // can be written with a single colon; route those to pseudo-element.
            if (!$isPseudoElement
                && in_array($name, ['before', 'after', 'first-line', 'first-letter'], true)
            ) {
                $isPseudoElement = true;
            }
            return $isPseudoElement
                ? new PseudoElementSelector($name)
                : new PseudoClassSelector($name);
        }
        if ($next instanceof FunctionToken) {
            $this->i++;
            $name = strtolower($next->name);
            $argTokens = $this->collectUntilMatchingParen();
            return $isPseudoElement
                ? $this->buildPseudoElementFunction($name, $argTokens)
                : $this->buildPseudoClassFunction($name, $argTokens);
        }
        throw new SelectorSyntaxException('Expected identifier or function after `:`');
    }

    /** @param list<Token> $argTokens */
    private function buildPseudoClassFunction(string $name, array $argTokens): PseudoClassSelector
    {
        switch ($name) {
            case 'is':
            case 'where':
            case 'has':
            case 'not':
                $forgiving = in_array($name, ['is', 'where'], true);
                $inner = self::parseTokensInner($argTokens, $forgiving);
                return new PseudoClassSelector($name, $inner);
            case 'nth-child':
            case 'nth-last-child':
            case 'nth-of-type':
            case 'nth-last-of-type':
                [$anb, $of] = AnPlusBParser::parseWithOf($argTokens);
                return new PseudoClassSelector($name, $of, $anb);
            case 'lang':
            case 'dir':
                $argText = self::serializeTokens($argTokens);
                return new PseudoClassSelector($name, argText: trim($argText));
            case 'host':
            case 'host-context':
                $inner = self::parseTokensInner($argTokens, false);
                return new PseudoClassSelector($name, $inner);
            default:
                // Unknown functional pseudo-class — keep as raw text.
                $argText = self::serializeTokens($argTokens);
                return new PseudoClassSelector($name, argText: trim($argText));
        }
    }

    /** @param list<Token> $argTokens */
    private function buildPseudoElementFunction(string $name, array $argTokens): PseudoElementSelector
    {
        $inner = match ($name) {
            'slotted', 'part', 'theme' => self::parseTokensInner($argTokens, false),
            default => self::parseTokensInner($argTokens, true),
        };
        return new PseudoElementSelector($name, $inner);
    }

    /** @param list<Token> $tokens */
    private static function parseTokensInner(array $tokens, bool $forgiving): SelectorList
    {
        $self = new self($tokens);
        $sels = $self->parseComplexSelectorList($forgiving);
        return new SelectorList(self::serializeTokens($tokens), $sels);
    }

    private function parseCombinator(): ?Combinator
    {
        $hadWhitespace = $this->skipWhitespace();
        $tok = $this->peek();
        if ($tok instanceof DelimToken) {
            switch ($tok->value) {
                case '>':
                    $this->i++;
                    $this->skipWhitespace();
                    return Combinator::Child;
                case '+':
                    $this->i++;
                    $this->skipWhitespace();
                    return Combinator::NextSibling;
                case '~':
                    $this->i++;
                    $this->skipWhitespace();
                    return Combinator::SubsequentSibling;
                case '|':
                    if ($this->peekAt(1) instanceof DelimToken && $this->peekAt(1)->value === '|') {
                        $this->i += 2;
                        $this->skipWhitespace();
                        return Combinator::Column;
                    }
            }
        }
        // Descendant: whitespace followed by something that starts a compound.
        if ($hadWhitespace && $this->startsCompound($tok)) {
            return Combinator::Descendant;
        }
        return null;
    }

    private function startsCompound(?Token $tok): bool
    {
        if ($tok === null) {
            return false;
        }
        if ($tok instanceof IdentToken
            || $tok instanceof HashToken
            || $tok instanceof LeftBracketToken
            || $tok instanceof ColonToken
        ) {
            return true;
        }
        if ($tok instanceof DelimToken) {
            return in_array($tok->value, ['.', '*', '|'], true);
        }
        return false;
    }

    /**
     * Collect tokens until the matching `)` of a function-token argument
     * list. The opening `(` was consumed as part of the FunctionToken.
     *
     * @return list<Token>
     */
    private function collectUntilMatchingParen(): array
    {
        $depth = 1;
        $out = [];
        while ($this->i < $this->count) {
            $t = $this->tokens[$this->i];
            if ($t instanceof RightParenToken) {
                $depth--;
                if ($depth === 0) {
                    $this->i++;
                    return $out;
                }
            }
            if ($t instanceof FunctionToken) {
                $depth++;
            }
            $out[] = $t;
            $this->i++;
        }
        // Implicit closer at EOF per CSS Syntax 3.
        return $out;
    }

    private function skipWhitespace(): bool
    {
        $had = false;
        while ($this->i < $this->count && $this->tokens[$this->i] instanceof WhitespaceToken) {
            $this->i++;
            $had = true;
        }
        return $had;
    }

    private function skipToNextSelector(): void
    {
        $depth = 0;
        while ($this->i < $this->count) {
            $t = $this->tokens[$this->i];
            if ($t instanceof CommaToken && $depth === 0) {
                return;
            }
            if ($t instanceof FunctionToken || $t instanceof LeftBracketToken) {
                $depth++;
            } elseif ($t instanceof RightParenToken || $t instanceof RightBracketToken) {
                if ($depth > 0) {
                    $depth--;
                }
            }
            $this->i++;
        }
    }

    private function eof(): bool
    {
        return $this->i >= $this->count;
    }

    private function peek(): ?Token
    {
        return $this->tokens[$this->i] ?? null;
    }

    private function peekAt(int $offset): ?Token
    {
        return $this->tokens[$this->i + $offset] ?? null;
    }

    /** @param list<Token> $tokens */
    private static function serializeTokens(array $tokens): string
    {
        $out = '';
        foreach ($tokens as $t) {
            $out .= self::serializeToken($t);
        }
        return $out;
    }

    /** @param list<Token> $tokens */
    private static function serializeTokenRange(array $tokens, int $from, int $to): string
    {
        $slice = array_slice($tokens, $from, $to - $from);
        return self::serializeTokens($slice);
    }

    private static function serializeToken(Token $t): string
    {
        return match (true) {
            $t instanceof IdentToken => $t->value,
            $t instanceof AtKeywordToken => '@' . $t->value,
            $t instanceof HashToken => '#' . $t->value,
            $t instanceof StringToken => '"' . str_replace('"', '\\"', $t->value) . '"',
            $t instanceof DelimToken => $t->value,
            $t instanceof CommaToken => ',',
            $t instanceof ColonToken => ':',
            $t instanceof WhitespaceToken => ' ',
            $t instanceof LeftBracketToken => '[',
            $t instanceof RightBracketToken => ']',
            $t instanceof FunctionToken => $t->name . '(',
            $t instanceof RightParenToken => ')',
            $t instanceof NumberToken => $t->type === NumberTokenType::Integer
                ? (string) (int) $t->value
                : (string) $t->value,
            $t instanceof DimensionToken => ($t->type === NumberTokenType::Integer
                ? (string) (int) $t->value
                : (string) $t->value) . $t->unit,
            default => '',
        };
    }
}
