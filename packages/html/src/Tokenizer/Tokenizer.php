<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

/**
 * WHATWG HTML §13.2.5 tokenizer.
 *
 * Phase 1B.2 + 1B.2-bis: all ~80 spec states implemented. Covers DOCTYPE
 * (including PUBLIC/SYSTEM identifiers), tags with every attribute form,
 * script-data with full escape/double-escape recovery, comments (including
 * nested-comment recovery), CDATA sections (entered when {@see self::$inForeignContent}
 * is true), and character references (numeric + named).
 *
 * Named character reference table (see {@see NamedCharacterReferences}) ships
 * the high-frequency subset of the spec's ~2200 entries. Generation of the
 * full table from the spec's `entities.json` is a separate deliverable
 * tracked in the rendering roadmap.
 *
 * Input preprocessing per WHATWG §13.2.3.5: CR/CRLF normalised to LF before
 * tokenizing. NULL handling is per-state (some emit U+FFFD, some emit raw,
 * all with parse-error tracking).
 */
final class Tokenizer
{
    public TokenizerState $state = TokenizerState::Data;
    public ?string $lastStartTagName = null; // for appropriate-end-tag check in RCDATA/RAWTEXT

    /**
     * Set to true by tree construction when the "adjusted current node" is
     * not in the HTML namespace (e.g. inside SVG or MathML). Affects the
     * MarkupDeclarationOpen state's handling of `[CDATA[`: in foreign content
     * we enter the CdataSection state; in HTML content it's a bogus comment.
     */
    public bool $inForeignContent = false;

    /** @var list<string> input as an array of UTF-8 single-codepoint strings */
    private array $chars;
    private int $length;
    private int $pos = 0;
    private bool $reconsume = false;
    private string $currentChar = '';

    private ?Token $currentToken = null;
    private TokenizerState $returnState = TokenizerState::Data;
    private string $tempBuffer = '';
    private int $characterReferenceCode = 0;

    /** @var list<Token> */
    private array $emitted = [];
    private int $emittedCursor = 0;
    /** @var list<ParseError> */
    private array $errors = [];
    private bool $done = false;

    public function __construct(string $input)
    {
        $normalised = $this->preprocess($input);
        $this->chars = $normalised === '' ? [] : (mb_str_split($normalised, 1, 'UTF-8') ?: []);
        $this->length = count($this->chars);
    }

    /**
     * Run the state machine to completion and return all tokens emitted, in
     * order, ending with an EofToken. Convenience for callers that don't need
     * mid-stream state interaction; tree construction uses {@see self::nextToken()}.
     *
     * @return list<Token>
     */
    public function tokenize(): array
    {
        while (!$this->done) {
            $this->step();
        }
        return $this->emitted;
    }

    /**
     * Pull the next token, advancing the state machine until at least one
     * token is emitted (or EOF). Tree construction drives this iteratively so
     * it can mutate {@see self::$state} (e.g. switching to RCDATA when
     * encountering `<title>`) between tokens.
     */
    public function nextToken(): ?Token
    {
        while ($this->emittedCursor >= count($this->emitted) && !$this->done) {
            $this->step();
        }
        if ($this->emittedCursor < count($this->emitted)) {
            return $this->emitted[$this->emittedCursor++];
        }
        return null;
    }

    /** @return list<ParseError> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function step(): void
    {
        match ($this->state) {
            TokenizerState::Data => $this->stateData(),
            TokenizerState::Rcdata => $this->stateRcdata(),
            TokenizerState::Rawtext => $this->stateRawtext(),
            TokenizerState::ScriptData => $this->stateScriptData(),
            TokenizerState::Plaintext => $this->statePlaintext(),
            TokenizerState::TagOpen => $this->stateTagOpen(),
            TokenizerState::EndTagOpen => $this->stateEndTagOpen(),
            TokenizerState::TagName => $this->stateTagName(),
            TokenizerState::RcdataLessThanSign => $this->stateRcdataLessThanSign(),
            TokenizerState::RcdataEndTagOpen => $this->stateRcdataEndTagOpen(),
            TokenizerState::RcdataEndTagName => $this->stateRcdataEndTagName(),
            TokenizerState::RawtextLessThanSign => $this->stateRawtextLessThanSign(),
            TokenizerState::RawtextEndTagOpen => $this->stateRawtextEndTagOpen(),
            TokenizerState::RawtextEndTagName => $this->stateRawtextEndTagName(),
            TokenizerState::ScriptDataLessThanSign => $this->stateScriptDataLessThanSign(),
            TokenizerState::ScriptDataEndTagOpen => $this->stateScriptDataEndTagOpen(),
            TokenizerState::ScriptDataEndTagName => $this->stateScriptDataEndTagName(),
            TokenizerState::ScriptDataEscapeStart => $this->stateScriptDataEscapeStart(),
            TokenizerState::ScriptDataEscapeStartDash => $this->stateScriptDataEscapeStartDash(),
            TokenizerState::ScriptDataEscaped => $this->stateScriptDataEscaped(),
            TokenizerState::ScriptDataEscapedDash => $this->stateScriptDataEscapedDash(),
            TokenizerState::ScriptDataEscapedDashDash => $this->stateScriptDataEscapedDashDash(),
            TokenizerState::ScriptDataEscapedLessThanSign => $this->stateScriptDataEscapedLessThanSign(),
            TokenizerState::ScriptDataEscapedEndTagOpen => $this->stateScriptDataEscapedEndTagOpen(),
            TokenizerState::ScriptDataEscapedEndTagName => $this->stateScriptDataEscapedEndTagName(),
            TokenizerState::ScriptDataDoubleEscapeStart => $this->stateScriptDataDoubleEscapeStart(),
            TokenizerState::ScriptDataDoubleEscaped => $this->stateScriptDataDoubleEscaped(),
            TokenizerState::ScriptDataDoubleEscapedDash => $this->stateScriptDataDoubleEscapedDash(),
            TokenizerState::ScriptDataDoubleEscapedDashDash => $this->stateScriptDataDoubleEscapedDashDash(),
            TokenizerState::ScriptDataDoubleEscapedLessThanSign => $this->stateScriptDataDoubleEscapedLessThanSign(),
            TokenizerState::ScriptDataDoubleEscapeEnd => $this->stateScriptDataDoubleEscapeEnd(),
            TokenizerState::BeforeAttributeName => $this->stateBeforeAttributeName(),
            TokenizerState::AttributeName => $this->stateAttributeName(),
            TokenizerState::AfterAttributeName => $this->stateAfterAttributeName(),
            TokenizerState::BeforeAttributeValue => $this->stateBeforeAttributeValue(),
            TokenizerState::AttributeValueDoubleQuoted => $this->stateAttributeValueDoubleQuoted(),
            TokenizerState::AttributeValueSingleQuoted => $this->stateAttributeValueSingleQuoted(),
            TokenizerState::AttributeValueUnquoted => $this->stateAttributeValueUnquoted(),
            TokenizerState::AfterAttributeValueQuoted => $this->stateAfterAttributeValueQuoted(),
            TokenizerState::SelfClosingStartTag => $this->stateSelfClosingStartTag(),
            TokenizerState::BogusComment => $this->stateBogusComment(),
            TokenizerState::MarkupDeclarationOpen => $this->stateMarkupDeclarationOpen(),
            TokenizerState::CommentStart => $this->stateCommentStart(),
            TokenizerState::CommentStartDash => $this->stateCommentStartDash(),
            TokenizerState::Comment => $this->stateComment(),
            TokenizerState::CommentLessThanSign => $this->stateCommentLessThanSign(),
            TokenizerState::CommentLessThanSignBang => $this->stateCommentLessThanSignBang(),
            TokenizerState::CommentLessThanSignBangDash => $this->stateCommentLessThanSignBangDash(),
            TokenizerState::CommentLessThanSignBangDashDash => $this->stateCommentLessThanSignBangDashDash(),
            TokenizerState::CommentEndDash => $this->stateCommentEndDash(),
            TokenizerState::CommentEnd => $this->stateCommentEnd(),
            TokenizerState::CommentEndBang => $this->stateCommentEndBang(),
            TokenizerState::Doctype => $this->stateDoctype(),
            TokenizerState::BeforeDoctypeName => $this->stateBeforeDoctypeName(),
            TokenizerState::DoctypeName => $this->stateDoctypeName(),
            TokenizerState::AfterDoctypeName => $this->stateAfterDoctypeName(),
            TokenizerState::AfterDoctypePublicKeyword => $this->stateAfterDoctypePublicKeyword(),
            TokenizerState::BeforeDoctypePublicIdentifier => $this->stateBeforeDoctypePublicIdentifier(),
            TokenizerState::DoctypePublicIdentifierDoubleQuoted => $this->stateDoctypePublicIdentifierDoubleQuoted(),
            TokenizerState::DoctypePublicIdentifierSingleQuoted => $this->stateDoctypePublicIdentifierSingleQuoted(),
            TokenizerState::AfterDoctypePublicIdentifier => $this->stateAfterDoctypePublicIdentifier(),
            TokenizerState::BetweenDoctypePublicAndSystemIdentifiers => $this->stateBetweenDoctypePublicAndSystemIdentifiers(),
            TokenizerState::AfterDoctypeSystemKeyword => $this->stateAfterDoctypeSystemKeyword(),
            TokenizerState::BeforeDoctypeSystemIdentifier => $this->stateBeforeDoctypeSystemIdentifier(),
            TokenizerState::DoctypeSystemIdentifierDoubleQuoted => $this->stateDoctypeSystemIdentifierDoubleQuoted(),
            TokenizerState::DoctypeSystemIdentifierSingleQuoted => $this->stateDoctypeSystemIdentifierSingleQuoted(),
            TokenizerState::AfterDoctypeSystemIdentifier => $this->stateAfterDoctypeSystemIdentifier(),
            TokenizerState::BogusDoctype => $this->stateBogusDoctype(),
            TokenizerState::CdataSection => $this->stateCdataSection(),
            TokenizerState::CdataSectionBracket => $this->stateCdataSectionBracket(),
            TokenizerState::CdataSectionEnd => $this->stateCdataSectionEnd(),
            TokenizerState::CharacterReference => $this->stateCharacterReference(),
            TokenizerState::NamedCharacterReference => $this->stateNamedCharacterReference(),
            TokenizerState::AmbiguousAmpersand => $this->stateAmbiguousAmpersand(),
            TokenizerState::NumericCharacterReference => $this->stateNumericCharacterReference(),
            TokenizerState::HexadecimalCharacterReferenceStart => $this->stateHexadecimalCharacterReferenceStart(),
            TokenizerState::DecimalCharacterReferenceStart => $this->stateDecimalCharacterReferenceStart(),
            TokenizerState::HexadecimalCharacterReference => $this->stateHexadecimalCharacterReference(),
            TokenizerState::DecimalCharacterReference => $this->stateDecimalCharacterReference(),
            TokenizerState::NumericCharacterReferenceEnd => $this->stateNumericCharacterReferenceEnd(),
        };
    }

    // ============================================================
    // Input / output helpers
    // ============================================================

    private function preprocess(string $input): string
    {
        // WHATWG §13.2.3.5 — strip a single leading U+FEFF byte order
        // mark (UTF-8 encoded as `\xEF\xBB\xBF`) before tokenising.
        // Without this the BOM tokenises as a text node and the first
        // following block child is pushed down by one line height.
        if (str_starts_with($input, "\xEF\xBB\xBF")) {
            $input = substr($input, 3);
        }
        // CRLF → LF, then CR → LF per WHATWG §13.2.3.5.
        $input = str_replace("\r\n", "\n", $input);
        $input = str_replace("\r", "\n", $input);
        return $input;
    }

    private function consume(): ?string
    {
        if ($this->reconsume) {
            $this->reconsume = false;
            return $this->currentChar === '' ? null : $this->currentChar;
        }
        if ($this->pos >= $this->length) {
            $this->currentChar = '';
            return null;
        }
        $this->currentChar = $this->chars[$this->pos++];
        return $this->currentChar;
    }

    private function reconsumeIn(TokenizerState $next): void
    {
        $this->state = $next;
        $this->reconsume = true;
    }

    private function peekRemaining(int $count): string
    {
        $start = $this->reconsume ? $this->pos - 1 : $this->pos;
        if ($start >= $this->length) {
            return '';
        }
        return implode('', array_slice($this->chars, $start, $count));
    }

    private function advance(int $count): void
    {
        // When reconsume is set, `pos` sits one past the reconsume char, so
        // the effective "start" for the advance is pos - 1. Compute from there
        // to keep the next consume() pointed at the correct character.
        $effectiveStart = $this->reconsume ? $this->pos - 1 : $this->pos;
        $this->pos = $effectiveStart + $count;
        $this->reconsume = false;
    }

    private function emit(Token $t): void
    {
        if ($t instanceof StartTagToken) {
            $this->lastStartTagName = $t->tagName;
        }
        $this->emitted[] = $t;
        if ($t instanceof EofToken) {
            $this->done = true;
        }
    }

    private function emitChar(string $data): void
    {
        $this->emit(new CharacterToken($data));
    }

    private function error(ParseErrorCode $code): void
    {
        $this->errors[] = new ParseError($code, $this->reconsume ? $this->pos - 1 : $this->pos);
    }

    private function currentTokenAsEnd(): EndTagToken
    {
        assert($this->currentToken instanceof EndTagToken);
        return $this->currentToken;
    }

    private function currentTokenAsTag(): StartTagToken|EndTagToken
    {
        assert($this->currentToken instanceof StartTagToken || $this->currentToken instanceof EndTagToken);
        return $this->currentToken;
    }

    private function currentTokenAsComment(): CommentToken
    {
        assert($this->currentToken instanceof CommentToken);
        return $this->currentToken;
    }

    private function currentTokenAsDoctype(): DoctypeToken
    {
        assert($this->currentToken instanceof DoctypeToken);
        return $this->currentToken;
    }

    private function startNewAttribute(StartTagToken|EndTagToken $tag): void
    {
        $tag->attributes[] = ['name' => '', 'value' => ''];
        $tag->currentAttribute = count($tag->attributes) - 1;
    }

    private function appendToCurrentAttributeName(StartTagToken|EndTagToken $tag, string $chars): void
    {
        assert($tag->currentAttribute !== null);
        $tag->attributes[$tag->currentAttribute]['name'] .= $chars;
    }

    private function appendToCurrentAttributeValue(StartTagToken|EndTagToken $tag, string $chars): void
    {
        assert($tag->currentAttribute !== null);
        $tag->attributes[$tag->currentAttribute]['value'] .= $chars;
    }

    /**
     * Per WHATWG: after a tag's attribute list is built, drop duplicate
     * attribute names (keep the first; emit unexpected-character-in-
     * attribute-name parse error for subsequent duplicates). Called when the
     * tag token is finalised.
     */
    private function dedupAttributes(StartTagToken|EndTagToken $tag): void
    {
        $seen = [];
        $out = [];
        foreach ($tag->attributes as $attr) {
            if (isset($seen[$attr['name']])) {
                $this->error(ParseErrorCode::UnexpectedCharacterInAttributeName);
                continue;
            }
            $seen[$attr['name']] = true;
            $out[] = $attr;
        }
        $tag->attributes = $out;
    }

    private function isAppropriateEndTag(EndTagToken $tag): bool
    {
        return $this->lastStartTagName !== null && $tag->tagName === $this->lastStartTagName;
    }

    // ============================================================
    // 13.2.5.1 Data state
    // ============================================================
    private function stateData(): void
    {
        $c = $this->consume();
        if ($c === '&') {
            $this->returnState = TokenizerState::Data;
            $this->state = TokenizerState::CharacterReference;
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::TagOpen;
            return;
        }
        if ($c === null) {
            $this->emit(new EofToken());
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
        }
        $this->emitChar($c);
    }

    // ============================================================
    // 13.2.5.2 RCDATA state
    // ============================================================
    private function stateRcdata(): void
    {
        $c = $this->consume();
        if ($c === '&') {
            $this->returnState = TokenizerState::Rcdata;
            $this->state = TokenizerState::CharacterReference;
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::RcdataLessThanSign;
            return;
        }
        if ($c === null) {
            $this->emit(new EofToken());
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->emitChar("\u{FFFD}");
            return;
        }
        $this->emitChar($c);
    }

    // ============================================================
    // 13.2.5.3 RAWTEXT state
    // ============================================================
    private function stateRawtext(): void
    {
        $c = $this->consume();
        if ($c === '<') {
            $this->state = TokenizerState::RawtextLessThanSign;
            return;
        }
        if ($c === null) {
            $this->emit(new EofToken());
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->emitChar("\u{FFFD}");
            return;
        }
        $this->emitChar($c);
    }

    // ============================================================
    // 13.2.5.4 Script data state
    // ============================================================
    private function stateScriptData(): void
    {
        $c = $this->consume();
        if ($c === '<') {
            $this->state = TokenizerState::ScriptDataLessThanSign;
            return;
        }
        if ($c === null) {
            $this->emit(new EofToken());
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->emitChar("\u{FFFD}");
            return;
        }
        $this->emitChar($c);
    }

    // ============================================================
    // 13.2.5.15–17 Script data less-than / end-tag states
    // ============================================================
    private function stateScriptDataLessThanSign(): void
    {
        $c = $this->consume();
        if ($c === '/') {
            $this->tempBuffer = '';
            $this->state = TokenizerState::ScriptDataEndTagOpen;
            return;
        }
        if ($c === '!') {
            $this->state = TokenizerState::ScriptDataEscapeStart;
            $this->emitChar('<');
            $this->emitChar('!');
            return;
        }
        $this->emitChar('<');
        $this->reconsumeIn(TokenizerState::ScriptData);
    }

    private function stateScriptDataEndTagOpen(): void
    {
        $c = $this->consume();
        if ($c !== null && self::isAsciiAlpha($c)) {
            $this->currentToken = new EndTagToken();
            $this->reconsumeIn(TokenizerState::ScriptDataEndTagName);
            return;
        }
        $this->emitChar('<');
        $this->emitChar('/');
        $this->reconsumeIn(TokenizerState::ScriptData);
    }

    private function stateScriptDataEndTagName(): void
    {
        $this->endTagNameAlternativeReturn(TokenizerState::ScriptData);
    }

    // ============================================================
    // 13.2.5.18–19 Script data escape start states
    // ============================================================
    private function stateScriptDataEscapeStart(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::ScriptDataEscapeStartDash;
            $this->emitChar('-');
            return;
        }
        $this->reconsumeIn(TokenizerState::ScriptData);
    }

    private function stateScriptDataEscapeStartDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::ScriptDataEscapedDashDash;
            $this->emitChar('-');
            return;
        }
        $this->reconsumeIn(TokenizerState::ScriptData);
    }

    // ============================================================
    // 13.2.5.20–22 Script data escaped states
    // ============================================================
    private function stateScriptDataEscaped(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::ScriptDataEscapedDash;
            $this->emitChar('-');
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::ScriptDataEscapedLessThanSign;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->emitChar("\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInScriptHtmlCommentLikeText);
            $this->emit(new EofToken());
            return;
        }
        $this->emitChar($c);
    }

    private function stateScriptDataEscapedDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::ScriptDataEscapedDashDash;
            $this->emitChar('-');
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::ScriptDataEscapedLessThanSign;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->state = TokenizerState::ScriptDataEscaped;
            $this->emitChar("\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInScriptHtmlCommentLikeText);
            $this->emit(new EofToken());
            return;
        }
        $this->state = TokenizerState::ScriptDataEscaped;
        $this->emitChar($c);
    }

    private function stateScriptDataEscapedDashDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->emitChar('-');
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::ScriptDataEscapedLessThanSign;
            return;
        }
        if ($c === '>') {
            $this->state = TokenizerState::ScriptData;
            $this->emitChar('>');
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->state = TokenizerState::ScriptDataEscaped;
            $this->emitChar("\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInScriptHtmlCommentLikeText);
            $this->emit(new EofToken());
            return;
        }
        $this->state = TokenizerState::ScriptDataEscaped;
        $this->emitChar($c);
    }

    // ============================================================
    // 13.2.5.23–25 Script data escaped less-than / end-tag states
    // ============================================================
    private function stateScriptDataEscapedLessThanSign(): void
    {
        $c = $this->consume();
        if ($c === '/') {
            $this->tempBuffer = '';
            $this->state = TokenizerState::ScriptDataEscapedEndTagOpen;
            return;
        }
        if ($c !== null && self::isAsciiAlpha($c)) {
            $this->tempBuffer = '';
            $this->emitChar('<');
            $this->reconsumeIn(TokenizerState::ScriptDataDoubleEscapeStart);
            return;
        }
        $this->emitChar('<');
        $this->reconsumeIn(TokenizerState::ScriptDataEscaped);
    }

    private function stateScriptDataEscapedEndTagOpen(): void
    {
        $c = $this->consume();
        if ($c !== null && self::isAsciiAlpha($c)) {
            $this->currentToken = new EndTagToken();
            $this->reconsumeIn(TokenizerState::ScriptDataEscapedEndTagName);
            return;
        }
        $this->emitChar('<');
        $this->emitChar('/');
        $this->reconsumeIn(TokenizerState::ScriptDataEscaped);
    }

    private function stateScriptDataEscapedEndTagName(): void
    {
        $this->endTagNameAlternativeReturn(TokenizerState::ScriptDataEscaped);
    }

    // ============================================================
    // 13.2.5.26–31 Script data double escape states
    // ============================================================
    private function stateScriptDataDoubleEscapeStart(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ' || $c === '/' || $c === '>') {
            $this->state = $this->tempBuffer === 'script'
                ? TokenizerState::ScriptDataDoubleEscaped
                : TokenizerState::ScriptDataEscaped;
            $this->emitChar($c);
            return;
        }
        if ($c !== null && self::isAsciiUpperAlpha($c)) {
            $this->tempBuffer .= strtolower($c);
            $this->emitChar($c);
            return;
        }
        if ($c !== null && self::isAsciiLowerAlpha($c)) {
            $this->tempBuffer .= $c;
            $this->emitChar($c);
            return;
        }
        $this->reconsumeIn(TokenizerState::ScriptDataEscaped);
    }

    private function stateScriptDataDoubleEscaped(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::ScriptDataDoubleEscapedDash;
            $this->emitChar('-');
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::ScriptDataDoubleEscapedLessThanSign;
            $this->emitChar('<');
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->emitChar("\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInScriptHtmlCommentLikeText);
            $this->emit(new EofToken());
            return;
        }
        $this->emitChar($c);
    }

    private function stateScriptDataDoubleEscapedDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::ScriptDataDoubleEscapedDashDash;
            $this->emitChar('-');
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::ScriptDataDoubleEscapedLessThanSign;
            $this->emitChar('<');
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->state = TokenizerState::ScriptDataDoubleEscaped;
            $this->emitChar("\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInScriptHtmlCommentLikeText);
            $this->emit(new EofToken());
            return;
        }
        $this->state = TokenizerState::ScriptDataDoubleEscaped;
        $this->emitChar($c);
    }

    private function stateScriptDataDoubleEscapedDashDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->emitChar('-');
            return;
        }
        if ($c === '<') {
            $this->state = TokenizerState::ScriptDataDoubleEscapedLessThanSign;
            $this->emitChar('<');
            return;
        }
        if ($c === '>') {
            $this->state = TokenizerState::ScriptData;
            $this->emitChar('>');
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->state = TokenizerState::ScriptDataDoubleEscaped;
            $this->emitChar("\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInScriptHtmlCommentLikeText);
            $this->emit(new EofToken());
            return;
        }
        $this->state = TokenizerState::ScriptDataDoubleEscaped;
        $this->emitChar($c);
    }

    private function stateScriptDataDoubleEscapedLessThanSign(): void
    {
        $c = $this->consume();
        if ($c === '/') {
            $this->tempBuffer = '';
            $this->state = TokenizerState::ScriptDataDoubleEscapeEnd;
            $this->emitChar('/');
            return;
        }
        $this->reconsumeIn(TokenizerState::ScriptDataDoubleEscaped);
    }

    private function stateScriptDataDoubleEscapeEnd(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ' || $c === '/' || $c === '>') {
            $this->state = $this->tempBuffer === 'script'
                ? TokenizerState::ScriptDataEscaped
                : TokenizerState::ScriptDataDoubleEscaped;
            $this->emitChar($c);
            return;
        }
        if ($c !== null && self::isAsciiUpperAlpha($c)) {
            $this->tempBuffer .= strtolower($c);
            $this->emitChar($c);
            return;
        }
        if ($c !== null && self::isAsciiLowerAlpha($c)) {
            $this->tempBuffer .= $c;
            $this->emitChar($c);
            return;
        }
        $this->reconsumeIn(TokenizerState::ScriptDataDoubleEscaped);
    }

    // ============================================================
    // 13.2.5.5 PLAINTEXT state
    // ============================================================
    private function statePlaintext(): void
    {
        $c = $this->consume();
        if ($c === null) {
            $this->emit(new EofToken());
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->emitChar("\u{FFFD}");
            return;
        }
        $this->emitChar($c);
    }

    // ============================================================
    // 13.2.5.6 Tag open state
    // ============================================================
    private function stateTagOpen(): void
    {
        $c = $this->consume();
        if ($c === '!') {
            $this->state = TokenizerState::MarkupDeclarationOpen;
            return;
        }
        if ($c === '/') {
            $this->state = TokenizerState::EndTagOpen;
            return;
        }
        if ($c !== null && self::isAsciiAlpha($c)) {
            $this->currentToken = new StartTagToken();
            $this->reconsumeIn(TokenizerState::TagName);
            return;
        }
        if ($c === '?') {
            $this->error(ParseErrorCode::UnexpectedQuestionMarkInsteadOfTagName);
            $this->currentToken = new CommentToken();
            $this->reconsumeIn(TokenizerState::BogusComment);
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofBeforeTagName);
            $this->emitChar('<');
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::InvalidFirstCharacterOfTagName);
        $this->emitChar('<');
        $this->reconsumeIn(TokenizerState::Data);
    }

    // ============================================================
    // 13.2.5.7 End tag open state
    // ============================================================
    private function stateEndTagOpen(): void
    {
        $c = $this->consume();
        if ($c !== null && self::isAsciiAlpha($c)) {
            $this->currentToken = new EndTagToken();
            $this->reconsumeIn(TokenizerState::TagName);
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::MissingEndTagName);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofBeforeTagName);
            $this->emitChar('<');
            $this->emitChar('/');
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::InvalidFirstCharacterOfTagName);
        $this->currentToken = new CommentToken();
        $this->reconsumeIn(TokenizerState::BogusComment);
    }

    // ============================================================
    // 13.2.5.8 Tag name state
    // ============================================================
    private function stateTagName(): void
    {
        $c = $this->consume();
        $tag = $this->currentTokenAsTag();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::BeforeAttributeName;
            return;
        }
        if ($c === '/') {
            $this->state = TokenizerState::SelfClosingStartTag;
            return;
        }
        if ($c === '>') {
            $this->finalizeAndEmitTag();
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c !== null && self::isAsciiUpperAlpha($c)) {
            $tag->tagName .= strtolower($c);
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $tag->tagName .= "\u{FFFD}";
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInTag);
            $this->emit(new EofToken());
            return;
        }
        $tag->tagName .= $c;
    }

    // ============================================================
    // 13.2.5.9–11 RCDATA less-than and end-tag states
    // ============================================================
    private function stateRcdataLessThanSign(): void
    {
        $c = $this->consume();
        if ($c === '/') {
            $this->tempBuffer = '';
            $this->state = TokenizerState::RcdataEndTagOpen;
            return;
        }
        $this->emitChar('<');
        $this->reconsumeIn(TokenizerState::Rcdata);
    }

    private function stateRcdataEndTagOpen(): void
    {
        $c = $this->consume();
        if ($c !== null && self::isAsciiAlpha($c)) {
            $this->currentToken = new EndTagToken();
            $this->reconsumeIn(TokenizerState::RcdataEndTagName);
            return;
        }
        $this->emitChar('<');
        $this->emitChar('/');
        $this->reconsumeIn(TokenizerState::Rcdata);
    }

    private function stateRcdataEndTagName(): void
    {
        $this->endTagNameAlternativeReturn(TokenizerState::Rcdata);
    }

    // ============================================================
    // 13.2.5.12–14 RAWTEXT less-than and end-tag states
    // ============================================================
    private function stateRawtextLessThanSign(): void
    {
        $c = $this->consume();
        if ($c === '/') {
            $this->tempBuffer = '';
            $this->state = TokenizerState::RawtextEndTagOpen;
            return;
        }
        $this->emitChar('<');
        $this->reconsumeIn(TokenizerState::Rawtext);
    }

    private function stateRawtextEndTagOpen(): void
    {
        $c = $this->consume();
        if ($c !== null && self::isAsciiAlpha($c)) {
            $this->currentToken = new EndTagToken();
            $this->reconsumeIn(TokenizerState::RawtextEndTagName);
            return;
        }
        $this->emitChar('<');
        $this->emitChar('/');
        $this->reconsumeIn(TokenizerState::Rawtext);
    }

    private function stateRawtextEndTagName(): void
    {
        $this->endTagNameAlternativeReturn(TokenizerState::Rawtext);
    }

    /**
     * Shared logic for RCDATA / RAWTEXT / Script end-tag-name states. If the
     * end tag matches the most recent start tag (the "appropriate end tag"),
     * transition like a normal tag close; otherwise emit characters and
     * return to the source state.
     */
    private function endTagNameAlternativeReturn(TokenizerState $sourceState): void
    {
        $c = $this->consume();
        $tag = $this->currentTokenAsEnd();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            if ($this->isAppropriateEndTag($tag)) {
                $this->state = TokenizerState::BeforeAttributeName;
                return;
            }
            $this->emitFakeOpeningChars($sourceState);
            return;
        }
        if ($c === '/') {
            if ($this->isAppropriateEndTag($tag)) {
                $this->state = TokenizerState::SelfClosingStartTag;
                return;
            }
            $this->emitFakeOpeningChars($sourceState);
            return;
        }
        if ($c === '>') {
            if ($this->isAppropriateEndTag($tag)) {
                $this->finalizeAndEmitTag();
                $this->state = TokenizerState::Data;
                return;
            }
            $this->emitFakeOpeningChars($sourceState);
            return;
        }
        if ($c !== null && self::isAsciiUpperAlpha($c)) {
            $tag->tagName .= strtolower($c);
            $this->tempBuffer .= $c;
            return;
        }
        if ($c !== null && self::isAsciiLowerAlpha($c)) {
            $tag->tagName .= $c;
            $this->tempBuffer .= $c;
            return;
        }
        $this->emitFakeOpeningChars($sourceState);
    }

    private function emitFakeOpeningChars(TokenizerState $sourceState): void
    {
        $this->emitChar('<');
        $this->emitChar('/');
        if ($this->tempBuffer !== '') {
            $this->emitChar($this->tempBuffer);
        }
        $this->reconsumeIn($sourceState);
    }

    // ============================================================
    // 13.2.5.32 Before attribute name state
    // ============================================================
    private function stateBeforeAttributeName(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '/' || $c === '>' || $c === null) {
            $this->reconsumeIn(TokenizerState::AfterAttributeName);
            return;
        }
        if ($c === '=') {
            $this->error(ParseErrorCode::UnexpectedEqualsSignBeforeAttributeName);
            $tag = $this->currentTokenAsTag();
            $this->startNewAttribute($tag);
            $this->appendToCurrentAttributeName($tag, '=');
            $this->state = TokenizerState::AttributeName;
            return;
        }
        $tag = $this->currentTokenAsTag();
        $this->startNewAttribute($tag);
        $this->reconsumeIn(TokenizerState::AttributeName);
    }

    // ============================================================
    // 13.2.5.33 Attribute name state
    // ============================================================
    private function stateAttributeName(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' '
            || $c === '/' || $c === '>' || $c === null
        ) {
            $this->reconsumeIn(TokenizerState::AfterAttributeName);
            return;
        }
        if ($c === '=') {
            $this->state = TokenizerState::BeforeAttributeValue;
            return;
        }
        $tag = $this->currentTokenAsTag();
        if (self::isAsciiUpperAlpha($c)) {
            $this->appendToCurrentAttributeName($tag, strtolower($c));
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->appendToCurrentAttributeName($tag, "\u{FFFD}");
            return;
        }
        if ($c === '"' || $c === "'" || $c === '<') {
            $this->error(ParseErrorCode::UnexpectedCharacterInAttributeName);
        }
        $this->appendToCurrentAttributeName($tag, $c);
    }

    // ============================================================
    // 13.2.5.34 After attribute name state
    // ============================================================
    private function stateAfterAttributeName(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '/') {
            $this->state = TokenizerState::SelfClosingStartTag;
            return;
        }
        if ($c === '=') {
            $this->state = TokenizerState::BeforeAttributeValue;
            return;
        }
        if ($c === '>') {
            $this->finalizeAndEmitTag();
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInTag);
            $this->emit(new EofToken());
            return;
        }
        $tag = $this->currentTokenAsTag();
        $this->startNewAttribute($tag);
        $this->reconsumeIn(TokenizerState::AttributeName);
    }

    // ============================================================
    // 13.2.5.35 Before attribute value state
    // ============================================================
    private function stateBeforeAttributeValue(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '"') {
            $this->state = TokenizerState::AttributeValueDoubleQuoted;
            return;
        }
        if ($c === "'") {
            $this->state = TokenizerState::AttributeValueSingleQuoted;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::MissingAttributeValue);
            $this->finalizeAndEmitTag();
            $this->state = TokenizerState::Data;
            return;
        }
        $this->reconsumeIn(TokenizerState::AttributeValueUnquoted);
    }

    // ============================================================
    // 13.2.5.36 Attribute value (double-quoted) state
    // ============================================================
    private function stateAttributeValueDoubleQuoted(): void
    {
        $c = $this->consume();
        if ($c === '"') {
            $this->state = TokenizerState::AfterAttributeValueQuoted;
            return;
        }
        if ($c === '&') {
            $this->returnState = TokenizerState::AttributeValueDoubleQuoted;
            $this->state = TokenizerState::CharacterReference;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), "\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInTag);
            $this->emit(new EofToken());
            return;
        }
        $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), $c);
    }

    // ============================================================
    // 13.2.5.37 Attribute value (single-quoted) state
    // ============================================================
    private function stateAttributeValueSingleQuoted(): void
    {
        $c = $this->consume();
        if ($c === "'") {
            $this->state = TokenizerState::AfterAttributeValueQuoted;
            return;
        }
        if ($c === '&') {
            $this->returnState = TokenizerState::AttributeValueSingleQuoted;
            $this->state = TokenizerState::CharacterReference;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), "\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInTag);
            $this->emit(new EofToken());
            return;
        }
        $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), $c);
    }

    // ============================================================
    // 13.2.5.38 Attribute value (unquoted) state
    // ============================================================
    private function stateAttributeValueUnquoted(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::BeforeAttributeName;
            return;
        }
        if ($c === '&') {
            $this->returnState = TokenizerState::AttributeValueUnquoted;
            $this->state = TokenizerState::CharacterReference;
            return;
        }
        if ($c === '>') {
            $this->finalizeAndEmitTag();
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), "\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInTag);
            $this->emit(new EofToken());
            return;
        }
        if ($c === '"' || $c === "'" || $c === '<' || $c === '=' || $c === '`') {
            $this->error(ParseErrorCode::UnexpectedCharacterInUnquotedAttributeValue);
        }
        $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), $c);
    }

    // ============================================================
    // 13.2.5.39 After attribute value (quoted) state
    // ============================================================
    private function stateAfterAttributeValueQuoted(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::BeforeAttributeName;
            return;
        }
        if ($c === '/') {
            $this->state = TokenizerState::SelfClosingStartTag;
            return;
        }
        if ($c === '>') {
            $this->finalizeAndEmitTag();
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInTag);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingWhitespaceBetweenAttributes);
        $this->reconsumeIn(TokenizerState::BeforeAttributeName);
    }

    // ============================================================
    // 13.2.5.40 Self-closing start tag state
    // ============================================================
    private function stateSelfClosingStartTag(): void
    {
        $c = $this->consume();
        if ($c === '>') {
            $tag = $this->currentTokenAsTag();
            $tag->selfClosing = true;
            if ($tag instanceof EndTagToken) {
                $this->error(ParseErrorCode::EndTagWithTrailingSolidus);
            }
            $this->finalizeAndEmitTag();
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInTag);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::UnexpectedSolidusInTag);
        $this->reconsumeIn(TokenizerState::BeforeAttributeName);
    }

    // ============================================================
    // 13.2.5.41 Bogus comment state
    // ============================================================
    private function stateBogusComment(): void
    {
        $c = $this->consume();
        $comment = $this->currentTokenAsComment();
        if ($c === '>') {
            $this->emit($comment);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->emit($comment);
            $this->emit(new EofToken());
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $comment->append("\u{FFFD}");
            return;
        }
        $comment->append($c);
    }

    // ============================================================
    // 13.2.5.42 Markup declaration open state
    // ============================================================
    private function stateMarkupDeclarationOpen(): void
    {
        // Peek ahead at the next characters. Two cases at Phase 1B.2:
        //   "--"   → comment start
        //   "doctype" (case-insensitive) → doctype
        // CDATA section ([CDATA[) is deferred to 1B.2-bis.
        $rest = $this->peekRemaining(7);
        if (str_starts_with($rest, '--')) {
            $this->advance(2);
            $this->currentToken = new CommentToken();
            $this->state = TokenizerState::CommentStart;
            return;
        }
        if (strcasecmp(substr($rest, 0, 7), 'doctype') === 0) {
            $this->advance(7);
            $this->state = TokenizerState::Doctype;
            return;
        }
        if (str_starts_with($rest, '[CDATA[')) {
            $this->advance(7);
            if ($this->inForeignContent) {
                $this->state = TokenizerState::CdataSection;
                return;
            }
            $this->error(ParseErrorCode::CdataInHtmlContent);
            $this->currentToken = new CommentToken('[CDATA[');
            $this->state = TokenizerState::BogusComment;
            return;
        }
        $this->error(ParseErrorCode::IncorrectlyOpenedComment);
        $this->currentToken = new CommentToken();
        $this->state = TokenizerState::BogusComment;
    }

    // ============================================================
    // 13.2.5.43–48 Comment states
    // ============================================================
    private function stateCommentStart(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::CommentStartDash;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::AbruptClosingOfEmptyComment);
            $this->emit($this->currentTokenAsComment());
            $this->state = TokenizerState::Data;
            return;
        }
        $this->reconsumeIn(TokenizerState::Comment);
    }

    private function stateCommentStartDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::CommentEnd;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::AbruptClosingOfEmptyComment);
            $this->emit($this->currentTokenAsComment());
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInComment);
            $this->emit($this->currentTokenAsComment());
            $this->emit(new EofToken());
            return;
        }
        $this->currentTokenAsComment()->append('-');
        $this->reconsumeIn(TokenizerState::Comment);
    }

    private function stateComment(): void
    {
        $c = $this->consume();
        $comment = $this->currentTokenAsComment();
        if ($c === '<') {
            $comment->append('<');
            $this->state = TokenizerState::CommentLessThanSign;
            return;
        }
        if ($c === '-') {
            $this->state = TokenizerState::CommentEndDash;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $comment->append("\u{FFFD}");
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInComment);
            $this->emit($comment);
            $this->emit(new EofToken());
            return;
        }
        $comment->append($c);
    }

    // ============================================================
    // 13.2.5.45–48 Comment less-than-sign / bang recovery states
    // ============================================================
    private function stateCommentLessThanSign(): void
    {
        $c = $this->consume();
        $comment = $this->currentTokenAsComment();
        if ($c === '!') {
            $comment->append('!');
            $this->state = TokenizerState::CommentLessThanSignBang;
            return;
        }
        if ($c === '<') {
            $comment->append('<');
            return;
        }
        $this->reconsumeIn(TokenizerState::Comment);
    }

    private function stateCommentLessThanSignBang(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::CommentLessThanSignBangDash;
            return;
        }
        $this->reconsumeIn(TokenizerState::Comment);
    }

    private function stateCommentLessThanSignBangDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::CommentLessThanSignBangDashDash;
            return;
        }
        $this->reconsumeIn(TokenizerState::CommentEndDash);
    }

    private function stateCommentLessThanSignBangDashDash(): void
    {
        $c = $this->consume();
        if ($c === '>' || $c === null) {
            $this->reconsumeIn(TokenizerState::CommentEnd);
            return;
        }
        $this->error(ParseErrorCode::NestedComment);
        $this->reconsumeIn(TokenizerState::CommentEnd);
    }

    // ============================================================
    // 13.2.5.69–71 CDATA section states (only valid in foreign content)
    // ============================================================
    private function stateCdataSection(): void
    {
        $c = $this->consume();
        if ($c === ']') {
            $this->state = TokenizerState::CdataSectionBracket;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInCdata);
            $this->emit(new EofToken());
            return;
        }
        // NULL inside CDATA is emitted verbatim per spec — no replacement.
        $this->emitChar($c);
    }

    private function stateCdataSectionBracket(): void
    {
        $c = $this->consume();
        if ($c === ']') {
            $this->state = TokenizerState::CdataSectionEnd;
            return;
        }
        $this->emitChar(']');
        $this->reconsumeIn(TokenizerState::CdataSection);
    }

    private function stateCdataSectionEnd(): void
    {
        $c = $this->consume();
        if ($c === ']') {
            $this->emitChar(']');
            return;
        }
        if ($c === '>') {
            $this->state = TokenizerState::Data;
            return;
        }
        $this->emitChar(']');
        $this->emitChar(']');
        $this->reconsumeIn(TokenizerState::CdataSection);
    }

    private function stateCommentEndDash(): void
    {
        $c = $this->consume();
        if ($c === '-') {
            $this->state = TokenizerState::CommentEnd;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInComment);
            $this->emit($this->currentTokenAsComment());
            $this->emit(new EofToken());
            return;
        }
        $this->currentTokenAsComment()->append('-');
        $this->reconsumeIn(TokenizerState::Comment);
    }

    private function stateCommentEnd(): void
    {
        $c = $this->consume();
        $comment = $this->currentTokenAsComment();
        if ($c === '>') {
            $this->emit($comment);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === '!') {
            $this->state = TokenizerState::CommentEndBang;
            return;
        }
        if ($c === '-') {
            $comment->append('-');
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInComment);
            $this->emit($comment);
            $this->emit(new EofToken());
            return;
        }
        $comment->append('--');
        $this->reconsumeIn(TokenizerState::Comment);
    }

    private function stateCommentEndBang(): void
    {
        $c = $this->consume();
        $comment = $this->currentTokenAsComment();
        if ($c === '-') {
            $comment->append('--!');
            $this->state = TokenizerState::CommentEndDash;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::IncorrectlyClosedComment);
            $this->emit($comment);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInComment);
            $this->emit($comment);
            $this->emit(new EofToken());
            return;
        }
        $comment->append('--!');
        $this->reconsumeIn(TokenizerState::Comment);
    }

    // ============================================================
    // 13.2.5.53 DOCTYPE state (and friends)
    // ============================================================
    private function stateDoctype(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::BeforeDoctypeName;
            return;
        }
        if ($c === '>') {
            $this->reconsumeIn(TokenizerState::BeforeDoctypeName);
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token = new DoctypeToken();
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingWhitespaceBeforeDoctypeName);
        $this->reconsumeIn(TokenizerState::BeforeDoctypeName);
    }

    private function stateBeforeDoctypeName(): void
    {
        $c = $this->consume();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        $token = new DoctypeToken();
        $this->currentToken = $token;
        if ($c !== null && self::isAsciiUpperAlpha($c)) {
            $token->name = strtolower($c);
            $this->state = TokenizerState::DoctypeName;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $token->name = "\u{FFFD}";
            $this->state = TokenizerState::DoctypeName;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::MissingDoctypeName);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $token->name = $c;
        $this->state = TokenizerState::DoctypeName;
    }

    private function stateDoctypeName(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        assert($token->name !== null);
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::AfterDoctypeName;
            return;
        }
        if ($c === '>') {
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c !== null && self::isAsciiUpperAlpha($c)) {
            $token->name .= strtolower($c);
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            $token->name .= "\u{FFFD}";
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $token->name .= $c;
    }

    private function stateAfterDoctypeName(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '>') {
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        // Look ahead for PUBLIC or SYSTEM (case-insensitive, including the
        // current char). pos points one past the current char; -1 to include it.
        $effectivePos = $this->reconsume ? $this->pos - 1 : $this->pos - 1;
        $window = implode('', array_slice($this->chars, $effectivePos, 6));
        if (strcasecmp($window, 'PUBLIC') === 0) {
            $this->pos = $effectivePos + 6;
            $this->reconsume = false;
            $this->state = TokenizerState::AfterDoctypePublicKeyword;
            return;
        }
        if (strcasecmp($window, 'SYSTEM') === 0) {
            $this->pos = $effectivePos + 6;
            $this->reconsume = false;
            $this->state = TokenizerState::AfterDoctypeSystemKeyword;
            return;
        }
        $this->error(ParseErrorCode::InvalidCharacterSequenceAfterDoctypeName);
        $token->forceQuirks = true;
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    // ============================================================
    // 13.2.5.57–67 DOCTYPE PUBLIC / SYSTEM identifier states
    // ============================================================
    private function stateAfterDoctypePublicKeyword(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::BeforeDoctypePublicIdentifier;
            return;
        }
        if ($c === '"') {
            $this->error(ParseErrorCode::MissingWhitespaceAfterDoctypePublicKeyword);
            $token->publicId = '';
            $this->state = TokenizerState::DoctypePublicIdentifierDoubleQuoted;
            return;
        }
        if ($c === "'") {
            $this->error(ParseErrorCode::MissingWhitespaceAfterDoctypePublicKeyword);
            $token->publicId = '';
            $this->state = TokenizerState::DoctypePublicIdentifierSingleQuoted;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::MissingDoctypePublicIdentifier);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingQuoteBeforeDoctypePublicIdentifier);
        $token->forceQuirks = true;
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    private function stateBeforeDoctypePublicIdentifier(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '"') {
            $token->publicId = '';
            $this->state = TokenizerState::DoctypePublicIdentifierDoubleQuoted;
            return;
        }
        if ($c === "'") {
            $token->publicId = '';
            $this->state = TokenizerState::DoctypePublicIdentifierSingleQuoted;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::MissingDoctypePublicIdentifier);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingQuoteBeforeDoctypePublicIdentifier);
        $token->forceQuirks = true;
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    private function stateDoctypePublicIdentifierDoubleQuoted(): void
    {
        $this->doctypeQuotedIdentifier(true, '"');
    }

    private function stateDoctypePublicIdentifierSingleQuoted(): void
    {
        $this->doctypeQuotedIdentifier(true, "'");
    }

    private function stateAfterDoctypePublicIdentifier(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::BetweenDoctypePublicAndSystemIdentifiers;
            return;
        }
        if ($c === '>') {
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === '"') {
            $this->error(ParseErrorCode::MissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers);
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierDoubleQuoted;
            return;
        }
        if ($c === "'") {
            $this->error(ParseErrorCode::MissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers);
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierSingleQuoted;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingQuoteBeforeDoctypeSystemIdentifier);
        $token->forceQuirks = true;
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    private function stateBetweenDoctypePublicAndSystemIdentifiers(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '>') {
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === '"') {
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierDoubleQuoted;
            return;
        }
        if ($c === "'") {
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierSingleQuoted;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingQuoteBeforeDoctypeSystemIdentifier);
        $token->forceQuirks = true;
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    private function stateAfterDoctypeSystemKeyword(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            $this->state = TokenizerState::BeforeDoctypeSystemIdentifier;
            return;
        }
        if ($c === '"') {
            $this->error(ParseErrorCode::MissingWhitespaceAfterDoctypeSystemKeyword);
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierDoubleQuoted;
            return;
        }
        if ($c === "'") {
            $this->error(ParseErrorCode::MissingWhitespaceAfterDoctypeSystemKeyword);
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierSingleQuoted;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::MissingDoctypeSystemIdentifier);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingQuoteBeforeDoctypeSystemIdentifier);
        $token->forceQuirks = true;
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    private function stateBeforeDoctypeSystemIdentifier(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '"') {
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierDoubleQuoted;
            return;
        }
        if ($c === "'") {
            $token->systemId = '';
            $this->state = TokenizerState::DoctypeSystemIdentifierSingleQuoted;
            return;
        }
        if ($c === '>') {
            $this->error(ParseErrorCode::MissingDoctypeSystemIdentifier);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        $this->error(ParseErrorCode::MissingQuoteBeforeDoctypeSystemIdentifier);
        $token->forceQuirks = true;
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    private function stateDoctypeSystemIdentifierDoubleQuoted(): void
    {
        $this->doctypeQuotedIdentifier(false, '"');
    }

    private function stateDoctypeSystemIdentifierSingleQuoted(): void
    {
        $this->doctypeQuotedIdentifier(false, "'");
    }

    private function stateAfterDoctypeSystemIdentifier(): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        if ($c === "\t" || $c === "\n" || $c === "\f" || $c === ' ') {
            return;
        }
        if ($c === '>') {
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        // Per spec: do NOT set force-quirks here. The DOCTYPE is otherwise
        // syntactically valid; trailing garbage just goes to a bogus state.
        $this->error(ParseErrorCode::UnexpectedCharacterAfterDoctypeSystemIdentifier);
        $this->reconsumeIn(TokenizerState::BogusDoctype);
    }

    /**
     * Shared logic for the four quoted-identifier states. $isPublic selects
     * which identifier field to append to; $terminator is " or '.
     */
    private function doctypeQuotedIdentifier(bool $isPublic, string $terminator): void
    {
        $c = $this->consume();
        $token = $this->currentTokenAsDoctype();
        $field = $isPublic ? 'publicId' : 'systemId';
        if ($c === $terminator) {
            $this->state = $isPublic
                ? TokenizerState::AfterDoctypePublicIdentifier
                : TokenizerState::AfterDoctypeSystemIdentifier;
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
            assert($token->{$field} !== null);
            $token->{$field} .= "\u{FFFD}";
            return;
        }
        if ($c === '>') {
            $this->error($isPublic
                ? ParseErrorCode::AbruptDoctypePublicIdentifier
                : ParseErrorCode::AbruptDoctypeSystemIdentifier);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->error(ParseErrorCode::EofInDoctype);
            $token->forceQuirks = true;
            $this->emit($token);
            $this->emit(new EofToken());
            return;
        }
        assert($token->{$field} !== null);
        $token->{$field} .= $c;
    }

    private function stateBogusDoctype(): void
    {
        $c = $this->consume();
        if ($c === '>') {
            $this->emit($this->currentTokenAsDoctype());
            $this->state = TokenizerState::Data;
            return;
        }
        if ($c === null) {
            $this->emit($this->currentTokenAsDoctype());
            $this->emit(new EofToken());
            return;
        }
        if ($c === "\u{0000}") {
            $this->error(ParseErrorCode::UnexpectedNullCharacter);
        }
        // Otherwise, ignore (no append per spec).
    }

    // ============================================================
    // 13.2.5.72–80 Character reference states
    // ============================================================
    private function stateCharacterReference(): void
    {
        $this->tempBuffer = '&';
        $c = $this->consume();
        if ($c === null) {
            $this->flushTempBufferToCharOrAttribute();
            $this->reconsumeIn($this->returnState);
            return;
        }
        if (self::isAsciiAlphanumeric($c)) {
            $this->reconsumeIn(TokenizerState::NamedCharacterReference);
            return;
        }
        if ($c === '#') {
            $this->tempBuffer .= '#';
            $this->state = TokenizerState::NumericCharacterReference;
            return;
        }
        $this->flushTempBufferToCharOrAttribute();
        $this->reconsumeIn($this->returnState);
    }

    private function stateNamedCharacterReference(): void
    {
        // Greedy longest-match against the in-memory table. WHATWG defines
        // matching against the spec's full ~2200-entry trie; our table is the
        // high-frequency subset (see NamedCharacterReferences).
        $start = $this->reconsume ? $this->pos - 1 : $this->pos;
        $bestMatch = null;
        $bestLen = 0;
        for ($len = 1; $len <= 32 && $start + $len <= $this->length; $len++) {
            $candidate = implode('', array_slice($this->chars, $start, $len));
            if (isset(NamedCharacterReferences::TABLE[$candidate])) {
                $bestMatch = $candidate;
                $bestLen = $len;
            }
        }

        if ($bestMatch !== null) {
            $hasSemicolon = str_ends_with($bestMatch, ';');
            $nextChar = $start + $bestLen < $this->length ? $this->chars[$start + $bestLen] : null;
            $inAttribute = $this->returnState === TokenizerState::AttributeValueDoubleQuoted
                || $this->returnState === TokenizerState::AttributeValueSingleQuoted
                || $this->returnState === TokenizerState::AttributeValueUnquoted;

            // Special case for attribute values + legacy entries: if the next
            // char is "=" or alphanumeric, don't decode (preserves
            // backward-compat with old URLs like ?foo=bar&copy=true).
            if (!$hasSemicolon && $inAttribute && $nextChar !== null
                && ($nextChar === '=' || self::isAsciiAlphanumeric($nextChar))
            ) {
                $this->tempBuffer = '&' . $bestMatch;
                $this->advance($bestLen);
                $this->reconsume = false;
                $this->flushTempBufferToCharOrAttribute();
                $this->state = $this->returnState;
                return;
            }

            if (!$hasSemicolon) {
                $this->error(ParseErrorCode::MissingSemicolonAfterCharacterReference);
            }
            $this->tempBuffer = NamedCharacterReferences::TABLE[$bestMatch];
            $this->advance($bestLen);
            $this->reconsume = false;
            $this->flushTempBufferToCharOrAttribute();
            $this->state = $this->returnState;
            return;
        }

        // No match — flush "&" and fall through to the ambiguous-ampersand state
        // to consume any remaining ASCII alphanumerics + ";" without decoding.
        $this->flushTempBufferToCharOrAttribute();
        $this->state = TokenizerState::AmbiguousAmpersand;
    }

    private function stateAmbiguousAmpersand(): void
    {
        $c = $this->consume();
        if ($c !== null && self::isAsciiAlphanumeric($c)) {
            $inAttribute = $this->returnState === TokenizerState::AttributeValueDoubleQuoted
                || $this->returnState === TokenizerState::AttributeValueSingleQuoted
                || $this->returnState === TokenizerState::AttributeValueUnquoted;
            if ($inAttribute) {
                $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), $c);
            } else {
                $this->emitChar($c);
            }
            return;
        }
        if ($c === ';') {
            $this->error(ParseErrorCode::UnknownNamedCharacterReference);
        }
        $this->reconsumeIn($this->returnState);
    }

    private function stateNumericCharacterReference(): void
    {
        $this->characterReferenceCode = 0;
        $c = $this->consume();
        if ($c === 'x' || $c === 'X') {
            $this->tempBuffer .= $c;
            $this->state = TokenizerState::HexadecimalCharacterReferenceStart;
            return;
        }
        $this->reconsumeIn(TokenizerState::DecimalCharacterReferenceStart);
    }

    private function stateHexadecimalCharacterReferenceStart(): void
    {
        $c = $this->consume();
        if ($c !== null && self::isAsciiHexDigit($c)) {
            $this->reconsumeIn(TokenizerState::HexadecimalCharacterReference);
            return;
        }
        $this->error(ParseErrorCode::AbsenceOfDigitsInNumericCharacterReference);
        $this->flushTempBufferToCharOrAttribute();
        $this->reconsumeIn($this->returnState);
    }

    private function stateDecimalCharacterReferenceStart(): void
    {
        $c = $this->consume();
        if ($c !== null && ctype_digit($c)) {
            $this->reconsumeIn(TokenizerState::DecimalCharacterReference);
            return;
        }
        $this->error(ParseErrorCode::AbsenceOfDigitsInNumericCharacterReference);
        $this->flushTempBufferToCharOrAttribute();
        $this->reconsumeIn($this->returnState);
    }

    private function stateHexadecimalCharacterReference(): void
    {
        $c = $this->consume();
        if ($c === null) {
            $this->error(ParseErrorCode::MissingSemicolonAfterCharacterReference);
            $this->state = TokenizerState::NumericCharacterReferenceEnd;
            $this->reconsume = true;
            return;
        }
        if (ctype_digit($c)) {
            $this->characterReferenceCode = $this->characterReferenceCode * 16 + (ord($c) - 0x30);
            return;
        }
        if ($c >= 'A' && $c <= 'F') {
            $this->characterReferenceCode = $this->characterReferenceCode * 16 + (ord($c) - 0x37);
            return;
        }
        if ($c >= 'a' && $c <= 'f') {
            $this->characterReferenceCode = $this->characterReferenceCode * 16 + (ord($c) - 0x57);
            return;
        }
        if ($c === ';') {
            $this->state = TokenizerState::NumericCharacterReferenceEnd;
            return;
        }
        $this->error(ParseErrorCode::MissingSemicolonAfterCharacterReference);
        $this->reconsumeIn(TokenizerState::NumericCharacterReferenceEnd);
    }

    private function stateDecimalCharacterReference(): void
    {
        $c = $this->consume();
        if ($c === null) {
            $this->error(ParseErrorCode::MissingSemicolonAfterCharacterReference);
            $this->state = TokenizerState::NumericCharacterReferenceEnd;
            $this->reconsume = true;
            return;
        }
        if (ctype_digit($c)) {
            $this->characterReferenceCode = $this->characterReferenceCode * 10 + (ord($c) - 0x30);
            return;
        }
        if ($c === ';') {
            $this->state = TokenizerState::NumericCharacterReferenceEnd;
            return;
        }
        $this->error(ParseErrorCode::MissingSemicolonAfterCharacterReference);
        $this->reconsumeIn(TokenizerState::NumericCharacterReferenceEnd);
    }

    private function stateNumericCharacterReferenceEnd(): void
    {
        $code = $this->characterReferenceCode;
        if ($code === 0) {
            $this->error(ParseErrorCode::NullCharacterReference);
            $code = 0xFFFD;
        } elseif ($code > 0x10FFFF) {
            $this->error(ParseErrorCode::CharacterReferenceOutsideUnicodeRange);
            $code = 0xFFFD;
        } elseif ($code >= 0xD800 && $code <= 0xDFFF) {
            $this->error(ParseErrorCode::SurrogateCharacterReference);
            $code = 0xFFFD;
        } elseif (isset(NamedCharacterReferences::NUMERIC_REPLACEMENTS[$code])) {
            $this->error(ParseErrorCode::ControlCharacterReference);
            $code = NamedCharacterReferences::NUMERIC_REPLACEMENTS[$code];
        }
        $this->tempBuffer = mb_chr($code, 'UTF-8');
        $this->flushTempBufferToCharOrAttribute();
        $this->state = $this->returnState;
    }

    private function flushTempBufferToCharOrAttribute(): void
    {
        $inAttribute = $this->returnState === TokenizerState::AttributeValueDoubleQuoted
            || $this->returnState === TokenizerState::AttributeValueSingleQuoted
            || $this->returnState === TokenizerState::AttributeValueUnquoted;
        if ($inAttribute) {
            $this->appendToCurrentAttributeValue($this->currentTokenAsTag(), $this->tempBuffer);
        } else {
            if ($this->tempBuffer !== '') {
                $this->emitChar($this->tempBuffer);
            }
        }
        $this->tempBuffer = '';
    }

    // ============================================================
    // Tag finalisation
    // ============================================================
    private function finalizeAndEmitTag(): void
    {
        $tag = $this->currentTokenAsTag();
        $this->dedupAttributes($tag);
        if ($tag instanceof EndTagToken && count($tag->attributes) > 0) {
            $this->error(ParseErrorCode::EndTagWithAttributes);
        }
        $this->emit($tag);
    }

    // ============================================================
    // Character classification helpers
    // ============================================================
    private static function isAsciiAlpha(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z');
    }

    private static function isAsciiUpperAlpha(string $c): bool
    {
        return $c >= 'A' && $c <= 'Z';
    }

    private static function isAsciiLowerAlpha(string $c): bool
    {
        return $c >= 'a' && $c <= 'z';
    }

    private static function isAsciiAlphanumeric(string $c): bool
    {
        return self::isAsciiAlpha($c) || ctype_digit($c);
    }

    private static function isAsciiHexDigit(string $c): bool
    {
        return ctype_digit($c) || ($c >= 'A' && $c <= 'F') || ($c >= 'a' && $c <= 'f');
    }
}
