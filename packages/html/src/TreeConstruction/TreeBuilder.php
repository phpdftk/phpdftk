<?php

declare(strict_types=1);

namespace Phpdftk\Html\TreeConstruction;

use Phpdftk\Html\Dom\Comment;
use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentMode;
use Phpdftk\Html\Dom\DocumentType;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\HTMLTemplateElement;
use Phpdftk\Html\Dom\Node;
use Phpdftk\Html\Dom\ShadowRootInit;
use Phpdftk\Html\Dom\ShadowRootMode;
use Phpdftk\Html\Dom\Text;
use Phpdftk\Html\ParserOptions;
use Phpdftk\Html\Tokenizer\CharacterToken;
use Phpdftk\Html\Tokenizer\CommentToken;
use Phpdftk\Html\Tokenizer\DoctypeToken;
use Phpdftk\Html\Tokenizer\EndTagToken;
use Phpdftk\Html\Tokenizer\EofToken;
use Phpdftk\Html\Tokenizer\StartTagToken;
use Phpdftk\Html\Tokenizer\Token;
use Phpdftk\Html\Tokenizer\Tokenizer;
use Phpdftk\Html\Tokenizer\TokenizerState;

/**
 * Tree construction per WHATWG HTML §13.2.6.
 *
 * Phase 1B.3 implements the common-path insertion modes:
 *  - Initial, BeforeHtml, BeforeHead, InHead, AfterHead, InBody, Text,
 *    AfterBody, AfterAfterBody
 *
 * Modes deferred to Phase 1B.3-bis:
 *  - All table-related modes (InTable, InTableText, InCaption, InColumnGroup,
 *    InTableBody, InRow, InCell) and foster parenting
 *  - InSelect, InSelectInTable
 *  - InTemplate (declarative shadow DOM tree construction)
 *  - InFrameset, AfterFrameset, AfterAfterFrameset
 *  - InHeadNoscript
 *  - Foreign content (SVG/MathML) insertion mode
 *  - Adoption agency algorithm (complex misnested-formatting recovery)
 *  - Full Noah's Ark dedup in ActiveFormattingElements::push
 *
 * Encountering a deferred mode triggers a NotImplementedYet exception with
 * the specific spec section that hasn't landed yet — designed to be
 * informative when html5lib-tests are wired up incrementally.
 */
final class TreeBuilder
{
    public InsertionMode $insertionMode = InsertionMode::Initial;
    public InsertionMode $originalInsertionMode = InsertionMode::Initial;

    public readonly Document $document;
    public readonly OpenElementsStack $openElements;
    public readonly ActiveFormattingElements $activeFormatting;

    public ?Element $headElement = null;
    public ?Element $formElement = null;

    /** Frameset-ok flag per spec. False once we've seen content that prevents <frameset>. */
    public bool $framesetOk = true;

    private bool $done = false;
    private ?Tokenizer $activeTokenizer = null;

    /**
     * Pending table character tokens: collected by InTableText, emitted as
     * either text into the table (if all whitespace) or foster-parented out
     * to the table's previous sibling (per §13.2.6.4.10).
     *
     * @var list<string>
     */
    private array $pendingTableCharacters = [];
    private bool $pendingTableCharactersHaveNonWhitespace = false;

    /**
     * Foster-parenting flag. Toggled true only when an InTable / InTableBody /
     * InRow handler falls through to "anything else" and dispatches into
     * modeInBody — i.e. when the *content* (not the table's own row/cell
     * structure) should land before the table.
     */
    private bool $fosterParenting = false;

    /**
     * Stack of template insertion modes. Pushed when a `<template>` start
     * tag is encountered; popped on `</template>`. Used by InTemplate to
     * recover the proper containing mode when nested templates close.
     *
     * @var list<InsertionMode>
     */
    private array $templateInsertionModes = [];

    public function __construct(
        public readonly ParserOptions $options = new ParserOptions(),
        ?Document $document = null,
    ) {
        $this->document = $document ?? new Document();
        $this->openElements = new OpenElementsStack();
        $this->activeFormatting = new ActiveFormattingElements();
    }

    /**
     * Parse an HTML fragment in the context of an element per WHATWG §13.4.
     * Builds a synthetic `<html>` root, primes the open-elements stack and
     * insertion mode based on the context, runs the parser, then returns the
     * root's children wrapped in a fresh DocumentFragment.
     */
    public function buildFragment(Tokenizer $tokenizer, Element $context): \Phpdftk\Html\Dom\DocumentFragment
    {
        // Step 4: create the html root and push it.
        $htmlRoot = $this->document->createElement('html');
        $this->document->appendChild($htmlRoot);
        $this->openElements->push($htmlRoot);

        // Step 5: if context is a template, push InTemplate onto template-mode stack.
        if ($context instanceof HTMLTemplateElement) {
            $this->templateInsertionModes[] = InsertionMode::InTemplate;
        }

        // Step 6: reset insertion mode appropriately based on context.
        $this->resetInsertionModeForFragment($context);

        // Step 7: find the appropriate form element by walking up from context.
        for ($node = $context; $node !== null; $node = $node->parentNode) {
            if ($node instanceof Element
                && $node->localName === 'form'
                && $node->namespaceURI === Document::HTML_NS
            ) {
                $this->formElement = $node;
                break;
            }
        }

        // Step 8: run the parser.
        $this->build($tokenizer);

        // Step 9: move html root's children into a fragment owned by the
        // CONTEXT's document (so the fragment is usable in that document).
        $fragment = $context->ownerDocument->createDocumentFragment();
        while ($htmlRoot->firstChild !== null) {
            $child = $htmlRoot->firstChild;
            $htmlRoot->removeChild($child);
            // The child's ownerDocument is the synthetic doc; for the host's
            // document to accept it cleanly we'd need to adopt it. For Phase
            // 1B.4 we accept the cross-document linkage; consumers that need
            // a strict same-document fragment should clone.
            $fragment->appendChild($child);
        }
        return $fragment;
    }

    /**
     * Reset insertion mode appropriately for fragment parsing — like the
     * full algorithm but treats the context element as the implicit bottom
     * of the open-elements stack. See WHATWG §13.2.4.1.
     */
    private function resetInsertionModeForFragment(Element $context): void
    {
        $name = $context->localName;
        $ns = $context->namespaceURI;
        if ($ns !== Document::HTML_NS) {
            $this->insertionMode = InsertionMode::InBody;
            return;
        }
        $this->insertionMode = match ($name) {
            'select' => InsertionMode::InSelect,
            'td', 'th' => InsertionMode::InCell,
            'tr' => InsertionMode::InRow,
            'tbody', 'thead', 'tfoot' => InsertionMode::InTableBody,
            'caption' => InsertionMode::InCaption,
            'colgroup' => InsertionMode::InColumnGroup,
            'table' => InsertionMode::InTable,
            'template' => InsertionMode::InTemplate,
            'head' => InsertionMode::InHead,
            'body' => InsertionMode::InBody,
            'frameset' => InsertionMode::InFrameset,
            'html' => InsertionMode::BeforeHead,
            default => InsertionMode::InBody,
        };
    }

    public function build(Tokenizer $tokenizer): Document
    {
        // Pull one token at a time so we can mutate the tokenizer state
        // between tokens — e.g. switching to RCDATA when we see <title>,
        // RAWTEXT for <style>, ScriptData for <script>.
        $this->activeTokenizer = $tokenizer;
        while (($token = $tokenizer->nextToken()) !== null) {
            $this->dispatch($token, $tokenizer);
            if ($this->done) {
                break;
            }
        }
        return $this->document;
    }

    private function dispatch(Token $token, Tokenizer $tokenizer): void
    {
        // Foreign content (SVG / MathML) dispatch per WHATWG §13.2.6.5. If
        // the adjusted current node is in a foreign namespace AND the token
        // isn't an explicit "breakout" trigger, process via the foreign-
        // content rules instead of the normal insertion mode.
        if ($this->shouldDispatchInForeignContent($token)) {
            $this->modeInForeignContent($token);
            return;
        }
        match ($this->insertionMode) {
            InsertionMode::Initial => $this->modeInitial($token),
            InsertionMode::BeforeHtml => $this->modeBeforeHtml($token),
            InsertionMode::BeforeHead => $this->modeBeforeHead($token),
            InsertionMode::InHead => $this->modeInHead($token, $tokenizer),
            InsertionMode::AfterHead => $this->modeAfterHead($token),
            InsertionMode::InBody => $this->modeInBody($token, $tokenizer),
            InsertionMode::Text => $this->modeText($token),
            InsertionMode::AfterBody => $this->modeAfterBody($token),
            InsertionMode::AfterAfterBody => $this->modeAfterAfterBody($token),
            InsertionMode::InTable => $this->modeInTable($token, $tokenizer),
            InsertionMode::InTableText => $this->modeInTableText($token, $tokenizer),
            InsertionMode::InCaption => $this->modeInCaption($token, $tokenizer),
            InsertionMode::InColumnGroup => $this->modeInColumnGroup($token, $tokenizer),
            InsertionMode::InTableBody => $this->modeInTableBody($token, $tokenizer),
            InsertionMode::InRow => $this->modeInRow($token, $tokenizer),
            InsertionMode::InCell => $this->modeInCell($token, $tokenizer),
            InsertionMode::InSelect => $this->modeInSelect($token),
            InsertionMode::InSelectInTable => $this->modeInSelectInTable($token, $tokenizer),
            InsertionMode::InTemplate => $this->modeInTemplate($token, $tokenizer),
            InsertionMode::InFrameset => $this->modeInFrameset($token),
            InsertionMode::AfterFrameset => $this->modeAfterFrameset($token, $tokenizer),
            InsertionMode::AfterAfterFrameset => $this->modeAfterAfterFrameset($token, $tokenizer),
            InsertionMode::InHeadNoscript => $this->modeInHeadNoscript($token, $tokenizer),
        };
    }

    // ============================================================
    // Initial
    // ============================================================
    private function modeInitial(Token $token): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            return;
        }
        if ($token instanceof CommentToken) {
            $this->document->appendChild($this->document->createComment($token->data));
            return;
        }
        if ($token instanceof DoctypeToken) {
            $name = $token->name ?? '';
            $publicId = $token->publicId ?? '';
            $systemId = $token->systemId ?? '';
            $this->document->appendChild(new DocumentType($this->document, $name, $publicId, $systemId));
            $this->document->mode = $this->resolveDocumentMode($token);
            $this->insertionMode = InsertionMode::BeforeHtml;
            return;
        }
        // No DOCTYPE — quirks mode.
        $this->document->mode = DocumentMode::Quirks;
        $this->insertionMode = InsertionMode::BeforeHtml;
        $this->reprocess($token);
    }

    private function resolveDocumentMode(DoctypeToken $token): DocumentMode
    {
        if ($token->forceQuirks) {
            return DocumentMode::Quirks;
        }
        $name = $token->name ?? '';
        if ($name !== 'html') {
            return DocumentMode::Quirks;
        }
        if ($token->publicId === null && ($token->systemId === null || strcasecmp($token->systemId, 'about:legacy-compat') === 0)) {
            return DocumentMode::NoQuirks;
        }
        // Public-ID-based legacy detection (subset; full table in §13.2.6.2)
        if (str_starts_with(strtolower($token->publicId ?? ''), '-//w3c//dtd html 4')) {
            return DocumentMode::LimitedQuirks;
        }
        return DocumentMode::NoQuirks;
    }

    // ============================================================
    // BeforeHtml
    // ============================================================
    private function modeBeforeHtml(Token $token): void
    {
        if ($token instanceof DoctypeToken) {
            return; // ignore
        }
        if ($token instanceof CommentToken) {
            $this->document->appendChild($this->document->createComment($token->data));
            return;
        }
        if ($this->isWhitespaceOnlyCharacter($token)) {
            return;
        }
        if ($token instanceof StartTagToken && $token->tagName === 'html') {
            $html = $this->createElementForToken($token);
            $this->document->appendChild($html);
            $this->openElements->push($html);
            $this->insertionMode = InsertionMode::BeforeHead;
            return;
        }
        // Anything else: create implicit <html>, then reprocess.
        $html = $this->document->createElement('html');
        $this->document->appendChild($html);
        $this->openElements->push($html);
        $this->insertionMode = InsertionMode::BeforeHead;
        $this->reprocess($token);
    }

    // ============================================================
    // BeforeHead
    // ============================================================
    private function modeBeforeHead(Token $token): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return; // ignore
        }
        if ($token instanceof StartTagToken && $token->tagName === 'html') {
            $this->processInBodyForStrayHtml($token);
            return;
        }
        if ($token instanceof StartTagToken && $token->tagName === 'head') {
            $this->headElement = $this->insertHtmlElement($token);
            $this->insertionMode = InsertionMode::InHead;
            return;
        }
        if ($token instanceof EndTagToken && in_array($token->tagName, ['head', 'body', 'html', 'br'], true)) {
            // Treat as anything-else (insert implicit head).
            $this->insertImplicitHeadAndReprocess($token);
            return;
        }
        if ($token instanceof EndTagToken) {
            return; // parse error, ignore
        }
        $this->insertImplicitHeadAndReprocess($token);
    }

    private function insertImplicitHeadAndReprocess(Token $token): void
    {
        $head = $this->document->createElement('head');
        $current = $this->openElements->currentNode();
        ($current ?? $this->document)->appendChild($head);
        $this->openElements->push($head);
        $this->headElement = $head;
        $this->insertionMode = InsertionMode::InHead;
        $this->reprocess($token);
    }

    // ============================================================
    // InHead
    // ============================================================
    private function modeInHead(Token $token, Tokenizer $tokenizer): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken) {
            if ($token->tagName === 'html') {
                $this->processInBodyForStrayHtml($token);
                return;
            }
            if (in_array($token->tagName, ['base', 'basefont', 'bgsound', 'link', 'meta'], true)) {
                $el = $this->insertHtmlElement($token);
                $this->openElements->pop();
                if ($token->selfClosing) {
                    // acknowledged
                }
                return;
            }
            if ($token->tagName === 'title') {
                $this->insertHtmlElement($token);
                $tokenizer->state = TokenizerState::Rcdata;
                $this->originalInsertionMode = $this->insertionMode;
                $this->insertionMode = InsertionMode::Text;
                return;
            }
            if (in_array($token->tagName, ['style', 'noframes'], true)) {
                $this->insertHtmlElement($token);
                $tokenizer->state = TokenizerState::Rawtext;
                $this->originalInsertionMode = $this->insertionMode;
                $this->insertionMode = InsertionMode::Text;
                return;
            }
            if ($token->tagName === 'script') {
                $this->insertHtmlElement($token);
                $tokenizer->state = TokenizerState::ScriptData;
                $this->originalInsertionMode = $this->insertionMode;
                $this->insertionMode = InsertionMode::Text;
                return;
            }
            if ($token->tagName === 'noscript') {
                if ($this->options->scriptingEnabled) {
                    // Scripting enabled → noscript content opaque (RAWTEXT + Text mode).
                    $this->insertHtmlElement($token);
                    $tokenizer->state = TokenizerState::Rawtext;
                    $this->originalInsertionMode = $this->insertionMode;
                    $this->insertionMode = InsertionMode::Text;
                    return;
                }
                // Scripting disabled (default) → InHeadNoscript mode gates which
                // elements can appear inside (only head-like flow content).
                $this->insertHtmlElement($token);
                $this->insertionMode = InsertionMode::InHeadNoscript;
                return;
            }
            if ($token->tagName === 'template') {
                // Per spec: the intended parent is the current node at the
                // time of the token, BEFORE the template is inserted onto
                // the stack.
                $intendedParent = $this->openElements->currentNode();

                $template = $this->insertHtmlElement($token);
                assert($template instanceof HTMLTemplateElement);

                // DSD path: shadowrootmode attribute present + parent eligible
                // + parent doesn't already have a shadow root.
                $shadowMode = $this->resolveShadowRootMode($token);
                if ($shadowMode !== null
                    && $intendedParent instanceof Element
                    && $intendedParent->shadowRoot === null
                    && $intendedParent->isShadowHostEligible()
                ) {
                    $init = new ShadowRootInit(
                        delegatesFocus: $this->tokenHasAttribute($token, 'shadowrootdelegatesfocus'),
                        clonable: $this->tokenHasAttribute($token, 'shadowrootclonable'),
                        serializable: $this->tokenHasAttribute($token, 'shadowrootserializable'),
                    );
                    try {
                        $shadowRoot = $intendedParent->attachShadow($shadowMode, $init);
                        $template->content = $shadowRoot;
                        $template->isDeclarativeShadowRoot = true;
                    } catch (\LogicException) {
                        // Eligibility check passed but attach failed (race condition
                        // with another DSD template, etc.) — fall back to normal.
                        $template->content = $this->document->createDocumentFragment();
                    }
                } else {
                    $template->content = $this->document->createDocumentFragment();
                }

                $this->activeFormatting->pushMarker();
                $this->framesetOk = false;
                $this->insertionMode = InsertionMode::InTemplate;
                $this->templateInsertionModes[] = InsertionMode::InTemplate;
                return;
            }
            if ($token->tagName === 'head') {
                return; // parse error, ignore
            }
            // Anything else: pop <head>, switch to AfterHead, reprocess.
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::AfterHead;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken) {
            if ($token->tagName === 'head') {
                $this->openElements->pop();
                $this->insertionMode = InsertionMode::AfterHead;
                return;
            }
            if (in_array($token->tagName, ['body', 'html', 'br'], true)) {
                $this->openElements->pop();
                $this->insertionMode = InsertionMode::AfterHead;
                $this->reprocess($token);
                return;
            }
            if ($token->tagName === 'template') {
                if (!$this->openElements->containsLocalName('template')) {
                    return; // parse error, ignore
                }
                // Capture the topmost template to check the DSD flag after popping.
                $template = null;
                $items = $this->openElements->items();
                for ($i = count($items) - 1; $i >= 0; $i--) {
                    $el = $items[$i];
                    if ($el->localName === 'template' && $el->namespaceURI === Document::HTML_NS) {
                        $template = $el;
                        break;
                    }
                }
                $this->openElements->generateImpliedEndTagsThoroughly();
                $this->openElements->popUntilLocalName('template');
                $this->activeFormatting->clearToLastMarker();
                array_pop($this->templateInsertionModes);
                $this->resetInsertionModeAppropriately();

                // Phase 1B.4: DSD templates are consumed during parse — remove
                // the template element from the light DOM. The shadow root on
                // the parent is the surviving artefact.
                if ($template instanceof HTMLTemplateElement
                    && $template->isDeclarativeShadowRoot
                    && $template->parentNode !== null
                ) {
                    $template->parentNode->removeChild($template);
                }
                return;
            }
            return; // parse error, ignore other end tags
        }
        if ($token instanceof EofToken) {
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::AfterHead;
            $this->reprocess($token);
            return;
        }
        // Character (non-whitespace): pop head, switch, reprocess.
        $this->openElements->pop();
        $this->insertionMode = InsertionMode::AfterHead;
        $this->reprocess($token);
    }

    // ============================================================
    // AfterHead
    // ============================================================
    private function modeAfterHead(Token $token): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken) {
            if ($token->tagName === 'html') {
                $this->processInBodyForStrayHtml($token);
                return;
            }
            if ($token->tagName === 'body') {
                $this->insertHtmlElement($token);
                $this->framesetOk = false;
                $this->insertionMode = InsertionMode::InBody;
                return;
            }
            if ($token->tagName === 'frameset') {
                $this->insertHtmlElement($token);
                $this->insertionMode = InsertionMode::InFrameset;
                return;
            }
            if (in_array($token->tagName, [
                'base', 'basefont', 'bgsound', 'link', 'meta', 'noframes',
                'script', 'style', 'template', 'title',
            ], true)) {
                // Parse error, but reprocess in InHead.
                if ($this->headElement !== null) {
                    $this->openElements->push($this->headElement);
                }
                $this->insertionMode = InsertionMode::InHead;
                $this->reprocess($token);
                if ($this->headElement !== null) {
                    $this->openElements->remove($this->headElement);
                }
                $this->insertionMode = InsertionMode::AfterHead;
                return;
            }
            if ($token->tagName === 'head') {
                return; // parse error, ignore
            }
            // Anything else: implicit <body>, switch, reprocess.
            $this->insertImplicitBody();
            $this->insertionMode = InsertionMode::InBody;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken) {
            if (in_array($token->tagName, ['body', 'html', 'br'], true)) {
                $body = $this->document->createElement('body');
                $current = $this->openElements->currentNode();
                ($current ?? $this->document)->appendChild($body);
                $this->openElements->push($body);
                $this->insertionMode = InsertionMode::InBody;
                $this->reprocess($token);
                return;
            }
            return; // parse error, ignore
        }
        // Anything else (non-whitespace character, EOF, etc.): insert implicit
        // <body>, switch to InBody, reprocess. Per WHATWG §13.2.6.4.7.
        $this->insertImplicitBody();
        $this->insertionMode = InsertionMode::InBody;
        $this->reprocess($token);
    }

    // ============================================================
    // InBody (foundation subset)
    // ============================================================
    private function modeInBody(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof CharacterToken) {
            $this->reconstructActiveFormatting();
            $this->insertCharacter($token);
            if (!$this->isWhitespaceOnlyCharacter($token)) {
                $this->framesetOk = false;
            }
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken) {
            $this->modeInBodyStartTag($token, $tokenizer);
            return;
        }
        if ($token instanceof EndTagToken) {
            $this->modeInBodyEndTag($token);
            return;
        }
        if ($token instanceof EofToken) {
            $this->done = true;
        }
    }

    private function modeInBodyStartTag(StartTagToken $token, Tokenizer $tokenizer): void
    {
        $tag = $token->tagName;

        if ($tag === 'html') {
            $this->processInBodyForStrayHtml($token);
            return;
        }

        // Head-like elements inside body — reprocess in InHead.
        if (in_array($tag, ['base', 'basefont', 'bgsound', 'link', 'meta', 'noframes', 'script', 'style', 'template', 'title'], true)) {
            $this->modeInHead($token, $tokenizer);
            return;
        }

        if ($tag === 'body') {
            // Parse error: foreign attributes are ignored at this scope in
            // Phase 1B.3; no merging into the open <body>.
            return;
        }
        if ($tag === 'frameset') {
            // Per spec: parse error unless framesetOk is true; the existing
            // body must be replaceable. If conditions aren't met, ignore.
            $items = $this->openElements->items();
            $bodyAtIndexOne = isset($items[1])
                && $items[1]->localName === 'body'
                && $items[1]->namespaceURI === Document::HTML_NS;
            if (!$bodyAtIndexOne || count($items) < 2 || !$this->framesetOk) {
                return; // parse error, ignore
            }
            // Remove the existing body from its parent and from the stack.
            $body = $items[1];
            $body->parentNode?->removeChild($body);
            while ($this->openElements->count() > 1) {
                $this->openElements->pop();
            }
            $this->insertHtmlElement($token);
            $this->insertionMode = InsertionMode::InFrameset;
            return;
        }

        // Block-level elements that close a currently open <p>.
        $closesParagraph = [
            'address', 'article', 'aside', 'blockquote', 'center', 'details', 'dialog',
            'dir', 'div', 'dl', 'fieldset', 'figcaption', 'figure', 'footer', 'header',
            'hgroup', 'main', 'menu', 'nav', 'ol', 'p', 'search', 'section', 'summary', 'ul',
        ];
        if (in_array($tag, $closesParagraph, true)) {
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->insertHtmlElement($token);
            return;
        }

        // Headings: like the closes-paragraph set, plus pop any open heading.
        if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $current = $this->openElements->currentNode();
            if ($current !== null && in_array($current->localName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                $this->openElements->pop();
            }
            $this->insertHtmlElement($token);
            return;
        }

        if ($tag === 'pre' || $tag === 'listing') {
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->insertHtmlElement($token);
            $this->framesetOk = false;
            // Per spec: skip a single leading LF if present (handled at
            // character-insertion time; tokenizer doesn't expose that here).
            return;
        }

        if ($tag === 'form') {
            if ($this->formElement !== null) {
                return; // parse error
            }
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->formElement = $this->insertHtmlElement($token);
            return;
        }

        if ($tag === 'li') {
            $this->framesetOk = false;
            for ($i = array_key_last($this->openElements->items()); $i !== null && $i >= 0; $i--) {
                $node = $this->openElements->items()[$i];
                if ($node->localName === 'li') {
                    $this->openElements->generateImpliedEndTags('li');
                    $this->openElements->popUntilLocalName('li');
                    break;
                }
                if (OpenElementsStack::isSpecialHtmlElement($node->localName)
                    && !in_array($node->localName, ['address', 'div', 'p'], true)) {
                    break;
                }
            }
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->insertHtmlElement($token);
            return;
        }

        if (in_array($tag, ['dd', 'dt'], true)) {
            $this->framesetOk = false;
            for ($i = array_key_last($this->openElements->items()); $i !== null && $i >= 0; $i--) {
                $node = $this->openElements->items()[$i];
                if (in_array($node->localName, ['dd', 'dt'], true)) {
                    $this->openElements->generateImpliedEndTags($node->localName);
                    $this->openElements->popUntilLocalName($node->localName);
                    break;
                }
                if (OpenElementsStack::isSpecialHtmlElement($node->localName)
                    && !in_array($node->localName, ['address', 'div', 'p'], true)) {
                    break;
                }
            }
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->insertHtmlElement($token);
            return;
        }

        // <a> has its own clause: if AFE already has an <a>, run AAA first.
        if ($tag === 'a') {
            $existing = $this->activeFormatting->findLastBetweenMarkerAnd('a');
            if ($existing !== null) {
                $this->adoptionAgency('a');
                $this->activeFormatting->remove($existing);
                $this->openElements->remove($existing);
            }
            $this->reconstructActiveFormatting();
            $el = $this->insertHtmlElement($token);
            $this->activeFormatting->push($el);
            return;
        }

        // <nobr> has special handling: if a nobr is in scope, run AAA first.
        if ($tag === 'nobr') {
            $this->reconstructActiveFormatting();
            if ($this->openElements->hasInScope('nobr')) {
                $this->adoptionAgency('nobr');
                $this->reconstructActiveFormatting();
            }
            $el = $this->insertHtmlElement($token);
            $this->activeFormatting->push($el);
            return;
        }

        // Other formatting elements — push onto AFE list after reconstruction.
        $formatting = ['b', 'big', 'code', 'em', 'font', 'i', 's', 'small', 'strike', 'strong', 'tt', 'u'];
        if (in_array($tag, $formatting, true)) {
            $this->reconstructActiveFormatting();
            $el = $this->insertHtmlElement($token);
            $this->activeFormatting->push($el);
            return;
        }

        // Void / self-closing-ish elements that clear framesetOk.
        if (in_array($tag, ['area', 'br', 'embed', 'img', 'keygen', 'wbr'], true)) {
            $this->reconstructActiveFormatting();
            $this->insertHtmlElement($token);
            $this->openElements->pop();
            $this->framesetOk = false;
            return;
        }
        // `source`, `track`, `param` are HTML 5 void elements that the
        // spec inserts + pops in InBody but doesn't clear framesetOk for
        // (see WHATWG §13.2.6.4.7 "A start tag whose tag name is one of:
        // param, source, track"). Practical reason for treating them as
        // void here: without it `<picture><source ...><img></picture>`
        // ends up nesting the `<img>` inside the `<source>`.
        if (in_array($tag, ['source', 'track', 'param'], true)) {
            $this->insertHtmlElement($token);
            $this->openElements->pop();
            return;
        }
        if ($tag === 'hr') {
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->insertHtmlElement($token);
            $this->openElements->pop();
            $this->framesetOk = false;
            return;
        }
        if ($tag === 'input') {
            $this->reconstructActiveFormatting();
            $this->insertHtmlElement($token);
            $this->openElements->pop();
            // Only "type=hidden" preserves frameset-ok; anything else flips it.
            $hasHiddenType = false;
            foreach ($token->attributes as $attr) {
                if ($attr['name'] === 'type' && strcasecmp($attr['value'], 'hidden') === 0) {
                    $hasHiddenType = true;
                    break;
                }
            }
            if (!$hasHiddenType) {
                $this->framesetOk = false;
            }
            return;
        }

        if ($tag === 'table') {
            if ($this->document->mode !== DocumentMode::Quirks
                && $this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->insertHtmlElement($token);
            $this->framesetOk = false;
            $this->insertionMode = InsertionMode::InTable;
            return;
        }

        if ($tag === 'select') {
            $this->reconstructActiveFormatting();
            $this->insertHtmlElement($token);
            $this->framesetOk = false;
            // If we're already inside a table-related mode, enter InSelectInTable.
            $previousMode = $this->insertionMode;
            $this->insertionMode = in_array($previousMode, [
                InsertionMode::InTable, InsertionMode::InCaption, InsertionMode::InTableBody,
                InsertionMode::InRow, InsertionMode::InCell,
            ], true) ? InsertionMode::InSelectInTable : InsertionMode::InSelect;
            return;
        }

        if ($tag === 'textarea') {
            $this->insertHtmlElement($token);
            $tokenizer->state = TokenizerState::Rcdata;
            $this->originalInsertionMode = $this->insertionMode;
            $this->framesetOk = false;
            $this->insertionMode = InsertionMode::Text;
            return;
        }

        if ($tag === 'xmp') {
            if ($this->openElements->hasInButtonScope('p')) {
                $this->closePElement();
            }
            $this->reconstructActiveFormatting();
            $this->framesetOk = false;
            $this->insertHtmlElement($token);
            $tokenizer->state = TokenizerState::Rawtext;
            $this->originalInsertionMode = $this->insertionMode;
            $this->insertionMode = InsertionMode::Text;
            return;
        }

        if ($tag === 'svg') {
            $this->reconstructActiveFormatting();
            $this->insertForeignElement($token, Document::SVG_NS, self::SVG_TAG_CASE_CORRECTIONS);
            if ($token->selfClosing) {
                $this->openElements->pop();
            }
            return;
        }
        if ($tag === 'math') {
            $this->reconstructActiveFormatting();
            $this->insertForeignElement($token, Document::MATHML_NS, []);
            if ($token->selfClosing) {
                $this->openElements->pop();
            }
            return;
        }

        if ($tag === 'iframe' || $tag === 'noembed') {
            $this->insertHtmlElement($token);
            $tokenizer->state = TokenizerState::Rawtext;
            $this->originalInsertionMode = $this->insertionMode;
            $this->framesetOk = false;
            $this->insertionMode = InsertionMode::Text;
            return;
        }

        // HTML Living Standard §13.2.6.4.7 — ruby base / container.
        // `<rb>` and `<rtc>` close any unclosed nested element down to
        // the open `<ruby>` if one is in scope.
        if ($tag === 'rb' || $tag === 'rtc') {
            if ($this->openElements->hasInScope('ruby')) {
                $this->openElements->generateImpliedEndTags();
                // Parse error if current node isn't `<ruby>`, but we
                // proceed (lenient mode) and insert anyway.
            }
            $this->insertHtmlElement($token);
            return;
        }

        // `<rt>` and `<rp>` close any unclosed nested element down to
        // the open `<ruby>` or `<rtc>` if a `<ruby>` is in scope —
        // the `<rtc>` exception lets `rt` annotations live inside a
        // shared container.
        if ($tag === 'rt' || $tag === 'rp') {
            if ($this->openElements->hasInScope('ruby')) {
                $this->openElements->generateImpliedEndTags('rtc');
                // Parse error if current node isn't `<ruby>` or `<rtc>`
                // — proceed in lenient mode.
            }
            $this->insertHtmlElement($token);
            return;
        }

        // Default: just insert (any other start tag).
        $this->reconstructActiveFormatting();
        $this->insertHtmlElement($token);
    }

    private function modeInBodyEndTag(EndTagToken $token): void
    {
        $tag = $token->tagName;

        if ($tag === 'template') {
            $this->modeInHead($token, $this->activeTokenizer ?? new Tokenizer(''));
            return;
        }

        if ($tag === 'body') {
            if (!$this->openElements->hasInScope('body')) {
                return; // parse error
            }
            $this->insertionMode = InsertionMode::AfterBody;
            return;
        }
        if ($tag === 'html') {
            if (!$this->openElements->hasInScope('body')) {
                return;
            }
            $this->insertionMode = InsertionMode::AfterBody;
            $this->reprocess($token);
            return;
        }

        $blockLike = [
            'address', 'article', 'aside', 'blockquote', 'button', 'center',
            'details', 'dialog', 'dir', 'div', 'dl', 'fieldset', 'figcaption',
            'figure', 'footer', 'header', 'hgroup', 'listing', 'main', 'menu',
            'nav', 'ol', 'pre', 'search', 'section', 'summary', 'ul',
        ];
        if (in_array($tag, $blockLike, true)) {
            if (!$this->openElements->hasInScope($tag)) {
                return; // parse error
            }
            $this->openElements->generateImpliedEndTags();
            $this->openElements->popUntilLocalName($tag);
            return;
        }

        if ($tag === 'form') {
            $node = $this->formElement;
            $this->formElement = null;
            if ($node === null || !$this->openElements->contains($node)) {
                return;
            }
            $this->openElements->generateImpliedEndTags();
            $this->openElements->remove($node);
            return;
        }

        if ($tag === 'p') {
            if (!$this->openElements->hasInButtonScope('p')) {
                // Per spec: insert an implicit <p>, then close it.
                $p = $this->document->createElement('p');
                $current = $this->openElements->currentNode();
                ($current ?? $this->document)->appendChild($p);
                $this->openElements->push($p);
            }
            $this->closePElement();
            return;
        }

        if ($tag === 'li') {
            if (!$this->openElements->hasInListItemScope('li')) {
                return;
            }
            $this->openElements->generateImpliedEndTags('li');
            $this->openElements->popUntilLocalName('li');
            return;
        }

        if (in_array($tag, ['dd', 'dt'], true)) {
            if (!$this->openElements->hasInScope($tag)) {
                return;
            }
            $this->openElements->generateImpliedEndTags($tag);
            $this->openElements->popUntilLocalName($tag);
            return;
        }

        if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
            $hasAny = false;
            foreach ($headings as $h) {
                if ($this->openElements->hasInScope($h)) {
                    $hasAny = true;
                    break;
                }
            }
            if (!$hasAny) {
                return;
            }
            $this->openElements->generateImpliedEndTags();
            $this->openElements->popUntilLocalName(...$headings);
            return;
        }

        // Formatting elements — run the adoption agency algorithm
        // (WHATWG §13.2.6.4.7 "any other end tag" / formatting tags subset).
        $formatting = ['a', 'b', 'big', 'code', 'em', 'font', 'i', 'nobr', 's', 'small', 'strike', 'strong', 'tt', 'u'];
        if (in_array($tag, $formatting, true)) {
            $this->adoptionAgency($tag);
            return;
        }

        // "Any other end tag" per spec — search up the open elements stack
        // for a matching element, generating implied end tags as we go.
        for ($i = array_key_last($this->openElements->items()); $i !== null && $i >= 0; $i--) {
            $node = $this->openElements->items()[$i];
            if ($node->localName === $tag && $node->namespaceURI === Document::HTML_NS) {
                $this->openElements->generateImpliedEndTags($tag);
                $this->openElements->popUntilElement($node);
                return;
            }
            if (OpenElementsStack::isSpecialHtmlElement($node->localName)) {
                return; // parse error, ignore
            }
        }
    }

    private function closePElement(): void
    {
        $this->openElements->generateImpliedEndTags('p');
        $this->openElements->popUntilLocalName('p');
    }

    private function insertImplicitBody(): Element
    {
        $body = $this->document->createElement('body');
        $current = $this->openElements->currentNode();
        ($current ?? $this->document)->appendChild($body);
        $this->openElements->push($body);
        return $body;
    }

    private function processInBodyForStrayHtml(StartTagToken $token): void
    {
        // Per spec parse-error case: merge attributes onto the <html> root
        // that aren't already present. Phase 1B.3 simplification: skip the
        // attribute merge; real-world impact is negligible for non-degenerate
        // input where the parser sees <html> only once.
    }

    // ============================================================
    // Text mode (RCDATA / RAWTEXT / Script)
    // ============================================================
    private function modeText(Token $token): void
    {
        if ($token instanceof CharacterToken) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof EofToken) {
            $this->openElements->pop();
            $this->insertionMode = $this->originalInsertionMode;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken) {
            // Pop and return to original insertion mode.
            $this->openElements->pop();
            $this->insertionMode = $this->originalInsertionMode;
            return;
        }
    }

    // ============================================================
    // AfterBody / AfterAfterBody
    // ============================================================
    private function modeAfterBody(Token $token): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            // Process as if InBody — text gets inserted into <body>.
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            // Append to <html>.
            $items = $this->openElements->items();
            $html = $items[0] ?? null;
            ($html ?? $this->document)->appendChild($this->document->createComment($token->data));
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken && $token->tagName === 'html') {
            $this->processInBodyForStrayHtml($token);
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'html') {
            $this->insertionMode = InsertionMode::AfterAfterBody;
            return;
        }
        if ($token instanceof EofToken) {
            $this->done = true;
            return;
        }
        // Parse error: switch back to InBody and reprocess.
        $this->insertionMode = InsertionMode::InBody;
        $this->reprocess($token);
    }

    private function modeAfterAfterBody(Token $token): void
    {
        if ($token instanceof CommentToken) {
            $this->document->appendChild($this->document->createComment($token->data));
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($this->isWhitespaceOnlyCharacter($token)) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof StartTagToken && $token->tagName === 'html') {
            $this->processInBodyForStrayHtml($token);
            return;
        }
        if ($token instanceof EofToken) {
            $this->done = true;
            return;
        }
        $this->insertionMode = InsertionMode::InBody;
        $this->reprocess($token);
    }

    // ============================================================
    // Insertion algorithms
    // ============================================================
    private function insertHtmlElement(StartTagToken $token): Element
    {
        $element = $this->createElementForToken($token);
        [$parent, $before] = $this->appropriatePlaceForInserting();
        if ($before !== null) {
            $parent->insertBefore($element, $before);
        } else {
            $parent->appendChild($element);
        }
        $this->openElements->push($element);
        return $element;
    }

    /**
     * "Appropriate place for inserting a node" per WHATWG §13.2.6.1. Foster
     * parenting applies when the current node is a table/tbody/tfoot/thead/tr
     * AND we're in a table-related insertion mode; in that case content is
     * inserted *before* the table rather than inside its element children.
     *
     * @return array{0: Node, 1: ?Node} parent + optional reference sibling
     */
    private function appropriatePlaceForInserting(): array
    {
        $target = $this->openElements->currentNode() ?? $this->document;
        // Template redirection: inserted children flow into the template's
        // content fragment (DocumentFragment for normal templates, ShadowRoot
        // for declarative shadow DOM) rather than into the template element
        // itself, per WHATWG §13.2.6.1 + DSD.
        if ($target instanceof HTMLTemplateElement && $target->content !== null) {
            return [$target->content, null];
        }
        if (!$this->fosterParenting) {
            return [$target, null];
        }
        if (!$target instanceof Element
            || !in_array($target->localName, ['table', 'tbody', 'tfoot', 'thead', 'tr'], true)
            || $target->namespaceURI !== Document::HTML_NS
        ) {
            return [$target, null];
        }
        // Foster-parent target: insert before the last table on the stack.
        for ($i = array_key_last($this->openElements->items()); $i !== null && $i >= 0; $i--) {
            $el = $this->openElements->items()[$i];
            if ($el->localName === 'table' && $el->namespaceURI === Document::HTML_NS) {
                $tableParent = $el->parentNode;
                if ($tableParent !== null) {
                    return [$tableParent, $el];
                }
                // No parent — fall back to the element before the table on the stack.
                if ($i > 0) {
                    return [$this->openElements->items()[$i - 1], null];
                }
            }
        }
        return [$target, null];
    }

    private function processAsInBodyWithFosterParenting(Token $token, Tokenizer $tokenizer): void
    {
        $previous = $this->fosterParenting;
        $this->fosterParenting = true;
        try {
            $this->modeInBody($token, $tokenizer);
        } finally {
            $this->fosterParenting = $previous;
        }
    }

    private function createElementForToken(StartTagToken $token): Element
    {
        $element = $this->document->createElement($token->tagName);
        foreach ($token->attributes as $attr) {
            // First-attribute-wins per WHATWG; dedup already done by tokenizer.
            if (!$element->hasAttribute($attr['name'])) {
                $element->setAttribute($attr['name'], $attr['value']);
            }
        }
        return $element;
    }

    private function insertCharacter(Token $token): void
    {
        if (!$token instanceof CharacterToken) {
            return;
        }
        [$parent, $before] = $this->appropriatePlaceForInserting();
        if ($before !== null) {
            // Foster-parented text: merge with the immediately-preceding text node if any.
            $prev = $before->previousSibling;
            if ($prev instanceof Text) {
                $prev->data .= $token->data;
                return;
            }
            $parent->insertBefore($this->document->createTextNode($token->data), $before);
            return;
        }
        $last = $parent->lastChild;
        if ($last instanceof Text) {
            $last->data .= $token->data;
            return;
        }
        $parent->appendChild($this->document->createTextNode($token->data));
    }

    private function insertComment(CommentToken $token): void
    {
        $current = $this->openElements->currentNode();
        ($current ?? $this->document)->appendChild($this->document->createComment($token->data));
    }

    /**
     * Adoption agency algorithm per WHATWG §13.2.6.4.7.
     *
     * The famous "Algorithm A" — recovers gracefully from misnested
     * formatting like `<b><i></b></i>`. Called from end tags for
     * `a`, `b`, `big`, `code`, `em`, `font`, `i`, `nobr`, `s`, `small`,
     * `strike`, `strong`, `tt`, `u`, and from start tags for `<a>` and
     * `<nobr>` when those elements are already on the active formatting
     * elements list / open elements stack.
     */
    private function adoptionAgency(string $subject): void
    {
        // Step 2: current node is a non-AFE HTML element with matching name.
        $current = $this->openElements->currentNode();
        if ($current !== null
            && $current->namespaceURI === Document::HTML_NS
            && $current->localName === $subject
            && !$this->activeFormatting->contains($current)
        ) {
            $this->openElements->pop();
            return;
        }

        // Step 3-4: outer loop, max 8 iterations.
        for ($outerLoop = 0; $outerLoop < 8; $outerLoop++) {
            // 4c: find the formatting element.
            $formattingElement = $this->activeFormatting->findLastBetweenMarkerAnd($subject);

            // 4d: no such element — "any other end tag" path.
            if ($formattingElement === null) {
                $this->processFormattingFallback($subject);
                return;
            }

            // 4e: formatting element not on open stack — parse error, drop from AFE.
            if (!$this->openElements->contains($formattingElement)) {
                $this->activeFormatting->remove($formattingElement);
                return;
            }

            // 4f: on stack but not in scope — parse error, return.
            if (!$this->openElements->hasInScope($formattingElement->localName)) {
                return;
            }

            // 4g: not the current node is a parse error but doesn't stop us.

            // 4h: furthest block — topmost special element below formatting element.
            $formattingIdx = $this->openElements->indexOf($formattingElement);
            if ($formattingIdx === null) {
                return;
            }
            $furthestBlock = null;
            $furthestBlockIdx = null;
            for ($i = $formattingIdx + 1; $i < $this->openElements->count(); $i++) {
                $node = $this->openElements->items()[$i];
                if (OpenElementsStack::isSpecialHtmlElement($node->localName)
                    && $node->namespaceURI === Document::HTML_NS) {
                    $furthestBlock = $node;
                    $furthestBlockIdx = $i;
                    break;
                }
            }

            // 4i: no furthest block — pop everything down to formatting element, drop from AFE.
            if ($furthestBlock === null || $furthestBlockIdx === null) {
                while ($this->openElements->currentNode() !== $formattingElement) {
                    $this->openElements->pop();
                }
                $this->openElements->pop();
                $this->activeFormatting->remove($formattingElement);
                return;
            }

            // 4j: common ancestor = element immediately above formatting element.
            $commonAncestor = $this->openElements->items()[$formattingIdx - 1] ?? null;
            if ($commonAncestor === null) {
                return;
            }

            // 4k: bookmark in AFE at formatting element's position.
            $bookmark = $this->activeFormatting->indexOf($formattingElement);
            if ($bookmark === null) {
                return;
            }

            // 4l-m: setup inner loop variables.
            $node = $furthestBlock;
            $nodeIdx = $furthestBlockIdx;
            $lastNode = $furthestBlock;

            for ($innerLoop = 1; $innerLoop < 20; $innerLoop++) {
                // 4n.ii: node = element immediately above the current node in the stack.
                $nodeIdx--;
                if ($nodeIdx < 0) {
                    break;
                }
                $node = $this->openElements->items()[$nodeIdx];

                // 4n.iii: stop when we reach formatting element.
                if ($node === $formattingElement) {
                    break;
                }

                // 4n.iv: kick out from AFE after 3 iterations.
                if ($innerLoop > 3 && $this->activeFormatting->contains($node)) {
                    $this->activeFormatting->remove($node);
                }

                // 4n.v: not in AFE — remove from open elements, continue (idx already adjusted).
                if (!$this->activeFormatting->contains($node)) {
                    $this->openElements->removeAt($nodeIdx);
                    // furthestBlockIdx and formattingIdx shift down by 1.
                    $furthestBlockIdx--;
                    continue;
                }

                // 4n.vi: clone node, replace in both AFE and open elements.
                $newNode = $this->document->createElement($node->localName);
                foreach ($node->attributes() as $attr) {
                    $newNode->setAttributeNode($attr);
                }
                $this->activeFormatting->replace($node, $newNode);
                $this->openElements->replaceAt($nodeIdx, $newNode);

                // 4n.vii: if last node was furthest block, move bookmark to after newNode in AFE.
                if ($lastNode === $furthestBlock) {
                    $newIdx = $this->activeFormatting->indexOf($newNode);
                    if ($newIdx !== null) {
                        $bookmark = $newIdx + 1;
                    }
                }

                // 4n.viii: detach lastNode and append it to newNode.
                if ($lastNode->parentNode !== null) {
                    $lastNode->parentNode->removeChild($lastNode);
                }
                $newNode->appendChild($lastNode);

                // 4n.ix: lastNode = node (after replacement, that's newNode).
                $lastNode = $newNode;
                $node = $newNode;
            }

            // 4o: insert lastNode under common ancestor (foster-parented if applicable).
            if ($lastNode->parentNode !== null) {
                $lastNode->parentNode->removeChild($lastNode);
            }
            // Use appropriatePlaceForInserting via override: we want $commonAncestor as target.
            // Foster-parenting only applies when commonAncestor is a table-context element AND
            // we're in a table-related mode. Phase 1B.3-bis simplification: append directly.
            $commonAncestor->appendChild($lastNode);

            // 4p: create new element for formatting element's token.
            $newFormatting = $this->document->createElement($formattingElement->localName);
            foreach ($formattingElement->attributes() as $attr) {
                $newFormatting->setAttributeNode($attr);
            }

            // 4q: move children of furthest block to new formatting element.
            while ($furthestBlock->firstChild !== null) {
                $newFormatting->appendChild($furthestBlock->firstChild);
            }

            // 4r: append new formatting element to furthest block.
            $furthestBlock->appendChild($newFormatting);

            // 4s: replace formatting element in AFE with new formatting at bookmark.
            $this->activeFormatting->remove($formattingElement);
            $afeCount = count($this->activeFormatting->entries());
            $bookmark = max(0, min($bookmark, $afeCount));
            $this->activeFormatting->insertAt($bookmark, $newFormatting);

            // 4t: remove formatting from open stack, insert new immediately after furthest block.
            $this->openElements->remove($formattingElement);
            $furthestBlockIdx = $this->openElements->indexOf($furthestBlock);
            if ($furthestBlockIdx === null) {
                return;
            }
            $this->openElements->insertAt($furthestBlockIdx + 1, $newFormatting);
        }
    }

    /**
     * AAA's "any other end tag" fallback (step 4d): search the open elements
     * stack for a matching element, generate implied end tags, pop. Mirrors
     * the same logic in modeInBodyEndTag's catch-all.
     */
    private function processFormattingFallback(string $subject): void
    {
        for ($i = array_key_last($this->openElements->items()); $i !== null && $i >= 0; $i--) {
            $node = $this->openElements->items()[$i];
            if ($node->localName === $subject && $node->namespaceURI === Document::HTML_NS) {
                $this->openElements->generateImpliedEndTags($subject);
                $this->openElements->popUntilElement($node);
                return;
            }
            if (OpenElementsStack::isSpecialHtmlElement($node->localName)) {
                return; // parse error, ignore
            }
        }
    }

    /**
     * Reconstruct the active formatting elements per §13.2.4.3. After certain
     * elements close, formatting elements that should still be active need
     * to be re-opened (e.g. text after a `</p>` that's still inside `<b>`).
     */
    private function reconstructActiveFormatting(): void
    {
        $entries = $this->activeFormatting->entries();
        if ($entries === []) {
            return;
        }
        $last = $entries[count($entries) - 1] ?? null;
        if ($last === null) {
            return; // marker — nothing to reconstruct
        }
        if ($this->openElements->contains($last)) {
            return; // already on the stack
        }

        // Walk back to find the first entry that's still on the stack or a marker.
        $i = count($entries) - 1;
        while ($i > 0) {
            $i--;
            $entry = $entries[$i];
            if ($entry === null) {
                $i++;
                break;
            }
            if ($this->openElements->contains($entry)) {
                $i++;
                break;
            }
        }

        // From i forward: clone and re-insert each entry.
        for (; $i < count($entries); $i++) {
            $entry = $entries[$i];
            if ($entry === null) {
                continue;
            }
            $clone = $this->document->createElement($entry->localName);
            foreach ($entry->attributes() as $attr) {
                $clone->setAttributeNode($attr);
            }
            $current = $this->openElements->currentNode();
            ($current ?? $this->document)->appendChild($clone);
            $this->openElements->push($clone);
            $this->activeFormatting->replace($entry, $clone);
        }
    }

    // ============================================================
    // InTable (§13.2.6.4.9)
    // ============================================================
    private function modeInTable(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof CharacterToken
            && $this->currentNodeIsTableContext()
        ) {
            $this->pendingTableCharacters = [];
            $this->pendingTableCharactersHaveNonWhitespace = false;
            $this->originalInsertionMode = $this->insertionMode;
            $this->insertionMode = InsertionMode::InTableText;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken) {
            $this->modeInTableStartTag($token, $tokenizer);
            return;
        }
        if ($token instanceof EndTagToken) {
            $this->modeInTableEndTag($token);
            return;
        }
        if ($token instanceof EofToken) {
            $this->modeInBody($token, $tokenizer);
        }
    }

    private function modeInTableStartTag(StartTagToken $token, Tokenizer $tokenizer): void
    {
        $tag = $token->tagName;
        if ($tag === 'caption') {
            $this->clearStackToTableContext();
            $this->activeFormatting->pushMarker();
            $this->insertHtmlElement($token);
            $this->insertionMode = InsertionMode::InCaption;
            return;
        }
        if ($tag === 'colgroup') {
            $this->clearStackToTableContext();
            $this->insertHtmlElement($token);
            $this->insertionMode = InsertionMode::InColumnGroup;
            return;
        }
        if ($tag === 'col') {
            $this->clearStackToTableContext();
            $colgroup = $this->document->createElement('colgroup');
            $current = $this->openElements->currentNode();
            ($current ?? $this->document)->appendChild($colgroup);
            $this->openElements->push($colgroup);
            $this->insertionMode = InsertionMode::InColumnGroup;
            $this->reprocess($token);
            return;
        }
        if (in_array($tag, ['tbody', 'tfoot', 'thead'], true)) {
            $this->clearStackToTableContext();
            $this->insertHtmlElement($token);
            $this->insertionMode = InsertionMode::InTableBody;
            return;
        }
        if (in_array($tag, ['td', 'th', 'tr'], true)) {
            $this->clearStackToTableContext();
            $synthetic = $this->document->createElement('tbody');
            $current = $this->openElements->currentNode();
            ($current ?? $this->document)->appendChild($synthetic);
            $this->openElements->push($synthetic);
            $this->insertionMode = InsertionMode::InTableBody;
            $this->reprocess($token);
            return;
        }
        if ($tag === 'table') {
            // Parse error: implicit </table>, then reprocess.
            if (!$this->openElements->hasInTableScope('table')) {
                return;
            }
            $this->openElements->popUntilLocalName('table');
            $this->resetInsertionModeAppropriately();
            $this->reprocess($token);
            return;
        }
        if (in_array($tag, ['style', 'script', 'template'], true)) {
            // Process via InHead (which knows how to switch tokenizer states).
            $this->modeInHead($token, $tokenizer);
            return;
        }
        if ($tag === 'input') {
            // Per spec: if type="hidden", insert normally; otherwise fall through to "anything else".
            $isHidden = false;
            foreach ($token->attributes as $attr) {
                if ($attr['name'] === 'type' && strcasecmp($attr['value'], 'hidden') === 0) {
                    $isHidden = true;
                    break;
                }
            }
            if ($isHidden) {
                $this->insertHtmlElement($token);
                $this->openElements->pop();
                return;
            }
        }
        if ($tag === 'form') {
            // Parse error per spec; we just bail with no form.
            return;
        }
        // "Anything else" — parse error; process the token under InBody with
        // foster-parenting enabled (handled by appropriatePlaceForInserting).
        $this->processAsInBodyWithFosterParenting($token, $tokenizer);
    }

    private function modeInTableEndTag(EndTagToken $token): void
    {
        $tag = $token->tagName;
        if ($tag === 'table') {
            if (!$this->openElements->hasInTableScope('table')) {
                return; // parse error
            }
            $this->openElements->popUntilLocalName('table');
            $this->resetInsertionModeAppropriately();
            return;
        }
        if (in_array($tag, ['body', 'caption', 'col', 'colgroup', 'html', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], true)) {
            return; // parse error, ignore
        }
        if (in_array($tag, ['style', 'script', 'template'], true)) {
            $this->modeInHead($token, $this->activeTokenizer ?? new Tokenizer(''));
            return;
        }
        // "Anything else" — process under InBody with foster parenting.
        $this->processAsInBodyWithFosterParenting($token, $this->activeTokenizer ?? new Tokenizer(''));
    }

    // ============================================================
    // InTableText (§13.2.6.4.10)
    // ============================================================
    private function modeInTableText(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof CharacterToken) {
            if ($token->data === "\u{0000}") {
                return; // parse error, drop NUL
            }
            $this->pendingTableCharacters[] = $token->data;
            if (preg_match('/[^\t\n\f\r ]/', $token->data) === 1) {
                $this->pendingTableCharactersHaveNonWhitespace = true;
            }
            return;
        }
        // Any other token: flush the buffered characters and return to original mode.
        $this->flushPendingTableCharacters();
        $this->insertionMode = $this->originalInsertionMode;
        $this->reprocess($token);
    }

    private function flushPendingTableCharacters(): void
    {
        if ($this->pendingTableCharacters === []) {
            return;
        }
        if ($this->pendingTableCharactersHaveNonWhitespace) {
            // Per spec: process each character via InBody rules with foster
            // parenting enabled.
            $combined = implode('', $this->pendingTableCharacters);
            $previous = $this->fosterParenting;
            $this->fosterParenting = true;
            try {
                $this->reconstructActiveFormatting();
                $this->insertCharacter(new CharacterToken($combined));
            } finally {
                $this->fosterParenting = $previous;
            }
            $this->framesetOk = false;
        } else {
            // All-whitespace: insert verbatim into table context.
            foreach ($this->pendingTableCharacters as $chunk) {
                $this->insertCharacter(new CharacterToken($chunk));
            }
        }
        $this->pendingTableCharacters = [];
        $this->pendingTableCharactersHaveNonWhitespace = false;
    }

    // ============================================================
    // InCaption (§13.2.6.4.11)
    // ============================================================
    private function modeInCaption(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof EndTagToken && $token->tagName === 'caption') {
            if (!$this->openElements->hasInTableScope('caption')) {
                return; // parse error
            }
            $this->openElements->generateImpliedEndTags();
            $this->openElements->popUntilLocalName('caption');
            $this->activeFormatting->clearToLastMarker();
            $this->insertionMode = InsertionMode::InTable;
            return;
        }
        if ($token instanceof StartTagToken
            && in_array($token->tagName, ['caption', 'col', 'colgroup', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], true)
        ) {
            // Implicit </caption>, then reprocess in InTable.
            if (!$this->openElements->hasInTableScope('caption')) {
                return;
            }
            $this->openElements->generateImpliedEndTags();
            $this->openElements->popUntilLocalName('caption');
            $this->activeFormatting->clearToLastMarker();
            $this->insertionMode = InsertionMode::InTable;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'table') {
            if (!$this->openElements->hasInTableScope('caption')) {
                return;
            }
            $this->openElements->generateImpliedEndTags();
            $this->openElements->popUntilLocalName('caption');
            $this->activeFormatting->clearToLastMarker();
            $this->insertionMode = InsertionMode::InTable;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken
            && in_array($token->tagName, ['body', 'col', 'colgroup', 'html', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], true)
        ) {
            return; // parse error
        }
        // Anything else: process under InBody.
        $this->modeInBody($token, $tokenizer);
    }

    // ============================================================
    // InColumnGroup (§13.2.6.4.12)
    // ============================================================
    private function modeInColumnGroup(Token $token, Tokenizer $tokenizer): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken && $token->tagName === 'col') {
            $this->insertHtmlElement($token);
            $this->openElements->pop();
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'colgroup') {
            $current = $this->openElements->currentNode();
            if ($current === null || $current->localName !== 'colgroup') {
                return; // parse error
            }
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::InTable;
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'col') {
            return; // parse error
        }
        if ($token instanceof StartTagToken && $token->tagName === 'template') {
            $this->modeInHead($token, $tokenizer);
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'template') {
            $this->modeInHead($token, $tokenizer);
            return;
        }
        // "Anything else" — implicit </colgroup>, then reprocess in InTable.
        $current = $this->openElements->currentNode();
        if ($current === null || $current->localName !== 'colgroup') {
            return; // parse error
        }
        $this->openElements->pop();
        $this->insertionMode = InsertionMode::InTable;
        $this->reprocess($token);
    }

    // ============================================================
    // InTableBody (§13.2.6.4.13)
    // ============================================================
    private function modeInTableBody(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof StartTagToken && $token->tagName === 'tr') {
            $this->clearStackToTableBodyContext();
            $this->insertHtmlElement($token);
            $this->insertionMode = InsertionMode::InRow;
            return;
        }
        if ($token instanceof StartTagToken && in_array($token->tagName, ['th', 'td'], true)) {
            // Implicit <tr>, then reprocess.
            $this->clearStackToTableBodyContext();
            $synthetic = $this->document->createElement('tr');
            $current = $this->openElements->currentNode();
            ($current ?? $this->document)->appendChild($synthetic);
            $this->openElements->push($synthetic);
            $this->insertionMode = InsertionMode::InRow;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken
            && in_array($token->tagName, ['tbody', 'tfoot', 'thead'], true)
        ) {
            if (!$this->openElements->hasInTableScope($token->tagName)) {
                return;
            }
            $this->clearStackToTableBodyContext();
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::InTable;
            return;
        }
        if (($token instanceof StartTagToken
                && in_array($token->tagName, ['caption', 'col', 'colgroup', 'tbody', 'tfoot', 'thead'], true))
            || ($token instanceof EndTagToken && $token->tagName === 'table')
        ) {
            $tbodyScope = $this->openElements->hasInTableScope('tbody')
                || $this->openElements->hasInTableScope('tfoot')
                || $this->openElements->hasInTableScope('thead');
            if (!$tbodyScope) {
                return; // parse error
            }
            $this->clearStackToTableBodyContext();
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::InTable;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken
            && in_array($token->tagName, ['body', 'caption', 'col', 'colgroup', 'html', 'td', 'th', 'tr'], true)
        ) {
            return; // parse error
        }
        $this->modeInTable($token, $tokenizer);
    }

    // ============================================================
    // InRow (§13.2.6.4.14)
    // ============================================================
    private function modeInRow(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof StartTagToken && in_array($token->tagName, ['th', 'td'], true)) {
            $this->clearStackToTableRowContext();
            $this->insertHtmlElement($token);
            $this->insertionMode = InsertionMode::InCell;
            $this->activeFormatting->pushMarker();
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'tr') {
            if (!$this->openElements->hasInTableScope('tr')) {
                return;
            }
            $this->clearStackToTableRowContext();
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::InTableBody;
            return;
        }
        if (($token instanceof StartTagToken
                && in_array($token->tagName, ['caption', 'col', 'colgroup', 'tbody', 'tfoot', 'thead', 'tr'], true))
            || ($token instanceof EndTagToken && $token->tagName === 'table')
        ) {
            if (!$this->openElements->hasInTableScope('tr')) {
                return;
            }
            $this->clearStackToTableRowContext();
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::InTableBody;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken && in_array($token->tagName, ['tbody', 'tfoot', 'thead'], true)) {
            if (!$this->openElements->hasInTableScope($token->tagName)) {
                return; // parse error
            }
            if (!$this->openElements->hasInTableScope('tr')) {
                return;
            }
            $this->clearStackToTableRowContext();
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::InTableBody;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken
            && in_array($token->tagName, ['body', 'caption', 'col', 'colgroup', 'html', 'td', 'th'], true)
        ) {
            return; // parse error
        }
        $this->modeInTable($token, $tokenizer);
    }

    // ============================================================
    // InCell (§13.2.6.4.15)
    // ============================================================
    private function modeInCell(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof EndTagToken && in_array($token->tagName, ['td', 'th'], true)) {
            if (!$this->openElements->hasInTableScope($token->tagName)) {
                return;
            }
            $this->openElements->generateImpliedEndTags();
            $this->openElements->popUntilLocalName($token->tagName);
            $this->activeFormatting->clearToLastMarker();
            $this->insertionMode = InsertionMode::InRow;
            return;
        }
        if ($token instanceof StartTagToken
            && in_array($token->tagName, ['caption', 'col', 'colgroup', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], true)
        ) {
            if (!$this->openElements->hasInTableScope('td')
                && !$this->openElements->hasInTableScope('th')
            ) {
                return; // parse error
            }
            $this->closeCell();
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken
            && in_array($token->tagName, ['body', 'caption', 'col', 'colgroup', 'html'], true)
        ) {
            return; // parse error
        }
        if ($token instanceof EndTagToken
            && in_array($token->tagName, ['table', 'tbody', 'tfoot', 'thead', 'tr'], true)
        ) {
            if (!$this->openElements->hasInTableScope($token->tagName)) {
                return;
            }
            $this->closeCell();
            $this->reprocess($token);
            return;
        }
        $this->modeInBody($token, $tokenizer);
    }

    // ============================================================
    // InFrameset / AfterFrameset / AfterAfterFrameset (§13.2.6.4.19–21)
    // ============================================================
    private function modeInFrameset(Token $token): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken) {
            $tag = $token->tagName;
            if ($tag === 'html') {
                $this->processInBodyForStrayHtml($token);
                return;
            }
            if ($tag === 'frameset') {
                $this->insertHtmlElement($token);
                return;
            }
            if ($tag === 'frame') {
                $this->insertHtmlElement($token);
                $this->openElements->pop(); // void
                return;
            }
            if ($tag === 'noframes') {
                $this->modeInHead($token, $this->activeTokenizer ?? new Tokenizer(''));
                return;
            }
            return; // parse error, ignore
        }
        if ($token instanceof EndTagToken) {
            if ($token->tagName === 'frameset') {
                $current = $this->openElements->currentNode();
                if ($current === null || ($current->localName === 'html' && $current->namespaceURI === Document::HTML_NS)) {
                    return; // parse error in fragment mode
                }
                $this->openElements->pop();
                // If not in fragment mode and current node is no longer a frameset, switch to AfterFrameset.
                $current = $this->openElements->currentNode();
                if ($current === null || $current->localName !== 'frameset') {
                    $this->insertionMode = InsertionMode::AfterFrameset;
                }
                return;
            }
            return; // parse error, ignore
        }
        if ($token instanceof EofToken) {
            // Parse error if current node isn't html.
            $this->done = true;
        }
    }

    private function modeAfterFrameset(Token $token, Tokenizer $tokenizer): void
    {
        if ($this->isWhitespaceOnlyCharacter($token)) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken) {
            if ($token->tagName === 'html') {
                $this->processInBodyForStrayHtml($token);
                return;
            }
            if ($token->tagName === 'noframes') {
                $this->modeInHead($token, $tokenizer);
                return;
            }
            return; // parse error, ignore
        }
        if ($token instanceof EndTagToken && $token->tagName === 'html') {
            $this->insertionMode = InsertionMode::AfterAfterFrameset;
            return;
        }
        if ($token instanceof EofToken) {
            $this->done = true;
        }
    }

    private function modeAfterAfterFrameset(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof CommentToken) {
            $this->document->appendChild($this->document->createComment($token->data));
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($this->isWhitespaceOnlyCharacter($token)) {
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof StartTagToken && $token->tagName === 'html') {
            $this->processInBodyForStrayHtml($token);
            return;
        }
        if ($token instanceof StartTagToken && $token->tagName === 'noframes') {
            $this->modeInHead($token, $tokenizer);
            return;
        }
        if ($token instanceof EofToken) {
            $this->done = true;
        }
    }

    // ============================================================
    // InHeadNoscript (§13.2.6.4.5) — only used when scripting is enabled
    // ============================================================
    private function modeInHeadNoscript(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof DoctypeToken) {
            return; // parse error
        }
        if ($token instanceof StartTagToken && $token->tagName === 'html') {
            $this->processInBodyForStrayHtml($token);
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'noscript') {
            $this->openElements->pop();
            $this->insertionMode = InsertionMode::InHead;
            return;
        }
        if ($this->isWhitespaceOnlyCharacter($token)
            || $token instanceof CommentToken
            || ($token instanceof StartTagToken && in_array($token->tagName, [
                'basefont', 'bgsound', 'link', 'meta', 'noframes', 'style',
            ], true))
        ) {
            $this->modeInHead($token, $tokenizer);
            return;
        }
        if ($token instanceof EndTagToken && $token->tagName === 'br') {
            // "Any other end tag" fallthrough — handled below.
        } elseif ($token instanceof EndTagToken) {
            return; // parse error, ignore
        }
        if ($token instanceof StartTagToken && in_array($token->tagName, ['head', 'noscript'], true)) {
            return; // parse error, ignore
        }
        // Anything else: parse error. Pop noscript, back to InHead, reprocess.
        $this->openElements->pop();
        $this->insertionMode = InsertionMode::InHead;
        $this->reprocess($token);
    }

    /**
     * SVG element name case corrections per WHATWG §13.2.6.5. The tokenizer
     * lower-cases tag names; SVG uses camelCase for several elements and
     * the parser is required to restore the canonical form.
     */
    private const array SVG_TAG_CASE_CORRECTIONS = [
        'altglyph' => 'altGlyph', 'altglyphdef' => 'altGlyphDef',
        'altglyphitem' => 'altGlyphItem', 'animatecolor' => 'animateColor',
        'animatemotion' => 'animateMotion', 'animatetransform' => 'animateTransform',
        'clippath' => 'clipPath', 'feblend' => 'feBlend',
        'fecolormatrix' => 'feColorMatrix', 'fecomponenttransfer' => 'feComponentTransfer',
        'fecomposite' => 'feComposite', 'feconvolvematrix' => 'feConvolveMatrix',
        'fediffuselighting' => 'feDiffuseLighting', 'fedisplacementmap' => 'feDisplacementMap',
        'fedistantlight' => 'feDistantLight', 'fedropshadow' => 'feDropShadow',
        'feflood' => 'feFlood', 'fefunca' => 'feFuncA', 'fefuncb' => 'feFuncB',
        'fefuncg' => 'feFuncG', 'fefuncr' => 'feFuncR', 'fegaussianblur' => 'feGaussianBlur',
        'feimage' => 'feImage', 'femerge' => 'feMerge', 'femergenode' => 'feMergeNode',
        'femorphology' => 'feMorphology', 'feoffset' => 'feOffset',
        'fepointlight' => 'fePointLight', 'fespecularlighting' => 'feSpecularLighting',
        'fespotlight' => 'feSpotLight', 'fetile' => 'feTile', 'feturbulence' => 'feTurbulence',
        'foreignobject' => 'foreignObject', 'glyphref' => 'glyphRef',
        'lineargradient' => 'linearGradient', 'radialgradient' => 'radialGradient',
        'textpath' => 'textPath',
    ];

    /**
     * HTML-element names that "break out" of foreign content per §13.2.6.5
     * "Any start tag whose tag name is one of: ..." — encountering one of
     * these in foreign content pops back to HTML.
     */
    private const array FOREIGN_BREAKOUT_TAGS = [
        'b', 'big', 'blockquote', 'body', 'br', 'center', 'code', 'dd', 'div',
        'dl', 'dt', 'em', 'embed', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head',
        'hr', 'i', 'img', 'li', 'listing', 'menu', 'meta', 'nobr', 'ol', 'p',
        'pre', 'ruby', 's', 'small', 'span', 'strong', 'strike', 'sub', 'sup',
        'table', 'tt', 'u', 'ul', 'var',
    ];

    private function shouldDispatchInForeignContent(Token $token): bool
    {
        $adjustedCurrent = $this->adjustedCurrentNode();
        if ($adjustedCurrent === null || $adjustedCurrent->namespaceURI === Document::HTML_NS) {
            return false;
        }
        // EOF: handled by the regular mode.
        if ($token instanceof EofToken) {
            return false;
        }
        // Per WHATWG §13.2.6.1 tree-construction dispatcher: at integration
        // points HTML rules win over foreign-content rules. Without these
        // gates, a break-out tag inside (say) <foreignObject> would pop back
        // to foreignObject, re-dispatch, and bounce into foreign content
        // again — infinite loop.
        if ($this->isMathmlTextIntegrationPoint($adjustedCurrent)) {
            if ($token instanceof CharacterToken) {
                return false;
            }
            if ($token instanceof StartTagToken
                && $token->tagName !== 'mglyph'
                && $token->tagName !== 'malignmark'
            ) {
                return false;
            }
        }
        if ($adjustedCurrent->namespaceURI === Document::MATHML_NS
            && $adjustedCurrent->localName === 'annotation-xml'
            && $token instanceof StartTagToken
            && $token->tagName === 'svg'
        ) {
            return false;
        }
        if ($this->isHtmlIntegrationPoint($adjustedCurrent)) {
            if ($token instanceof StartTagToken || $token instanceof CharacterToken) {
                return false;
            }
        }
        return true;
    }

    private function adjustedCurrentNode(): ?Element
    {
        // Fragment parsing isn't wired in yet; adjusted current node === current node.
        return $this->openElements->currentNode();
    }

    /**
     * Insert a foreign element (SVG or MathML) onto the stack with namespace
     * applied. $caseTable optionally remaps lower-case tokenizer names back
     * to canonical camelCase (used for SVG element names).
     *
     * @param array<string, string> $caseTable
     */
    private function insertForeignElement(StartTagToken $token, string $namespace, array $caseTable): Element
    {
        $localName = $caseTable[$token->tagName] ?? $token->tagName;
        $element = $this->document->createElement($localName, $namespace);
        foreach ($token->attributes as $attr) {
            $element->setAttribute($attr['name'], $attr['value']);
        }
        [$parent, $before] = $this->appropriatePlaceForInserting();
        if ($before !== null) {
            $parent->insertBefore($element, $before);
        } else {
            $parent->appendChild($element);
        }
        $this->openElements->push($element);
        return $element;
    }

    /**
     * Foreign content processing per §13.2.6.5. Phase 1B.3-bis implements the
     * common path: namespaced element insertion, character data, comments,
     * end-tag matching, and the "break out" tags that pop back to HTML.
     */
    private function modeInForeignContent(Token $token): void
    {
        if ($token instanceof CharacterToken) {
            if ($token->data === "\u{0000}") {
                $this->insertCharacter(new CharacterToken("\u{FFFD}"));
                return;
            }
            if (preg_match('/[^\t\n\f\r ]/', $token->data) === 1) {
                $this->framesetOk = false;
            }
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return; // parse error, ignore
        }
        if ($token instanceof StartTagToken) {
            $tag = $token->tagName;
            // Break-out check.
            $isFontWithBreakoutAttr = false;
            if ($tag === 'font') {
                foreach ($token->attributes as $attr) {
                    if (in_array($attr['name'], ['color', 'face', 'size'], true)) {
                        $isFontWithBreakoutAttr = true;
                        break;
                    }
                }
            }
            if (in_array($tag, self::FOREIGN_BREAKOUT_TAGS, true) || $isFontWithBreakoutAttr) {
                // Pop until we're back in HTML or at a foreign-text-integration point.
                while (!$this->openElements->isEmpty()) {
                    $current = $this->openElements->currentNode();
                    if ($current === null
                        || $current->namespaceURI === Document::HTML_NS
                        || $this->isMathmlTextIntegrationPoint($current)
                        || $this->isHtmlIntegrationPoint($current)
                    ) {
                        break;
                    }
                    $this->openElements->pop();
                }
                $this->dispatch($token, $this->activeTokenizer ?? new Tokenizer(''));
                return;
            }
            $adjusted = $this->adjustedCurrentNode();
            if ($adjusted === null) {
                return;
            }
            $namespace = $adjusted->namespaceURI;
            $caseTable = $namespace === Document::SVG_NS ? self::SVG_TAG_CASE_CORRECTIONS : [];
            $this->insertForeignElement($token, $namespace, $caseTable);
            if ($token->selfClosing) {
                $this->openElements->pop();
            }
            return;
        }
        if ($token instanceof EndTagToken) {
            $tag = $token->tagName;
            $items = $this->openElements->items();
            $i = array_key_last($items);
            if ($i === null) {
                return;
            }
            // Per spec: if current node's local name (case-insensitive for
            // foreign) doesn't match the end tag, walk up looking for a match.
            $node = $items[$i];
            if (strcasecmp($node->localName, $tag) !== 0) {
                // parse error, but continue walking
            }
            for (; $i >= 0; $i--) {
                $node = $items[$i];
                if (strcasecmp($node->localName, $tag) === 0) {
                    while ($this->openElements->count() - 1 > $i) {
                        $this->openElements->pop();
                    }
                    $this->openElements->pop();
                    return;
                }
                if ($node->namespaceURI === Document::HTML_NS) {
                    // Process per regular insertion mode rules.
                    match ($this->insertionMode) {
                        InsertionMode::InBody => $this->modeInBody($token, $this->activeTokenizer ?? new Tokenizer('')),
                        default => null,
                    };
                    return;
                }
            }
        }
    }

    /**
     * MathML text integration points per spec: mi, mo, mn, ms, mtext in the
     * MathML namespace. Phase 1B.3-bis ships this for the break-out check;
     * full integration-point handling (which lets HTML breach into mtext etc.)
     * lands in a follow-up.
     */
    private function isMathmlTextIntegrationPoint(Element $el): bool
    {
        return $el->namespaceURI === Document::MATHML_NS
            && in_array($el->localName, ['mi', 'mo', 'mn', 'ms', 'mtext'], true);
    }

    /**
     * HTML integration points per spec: `<annotation-xml>` with encoding
     * text/html or application/xhtml+xml (MathML), and `<foreignObject>`,
     * `<desc>`, `<title>` in SVG.
     */
    private function isHtmlIntegrationPoint(Element $el): bool
    {
        if ($el->namespaceURI === Document::MATHML_NS && $el->localName === 'annotation-xml') {
            $enc = strtolower($el->getAttribute('encoding') ?? '');
            return $enc === 'text/html' || $enc === 'application/xhtml+xml';
        }
        if ($el->namespaceURI === Document::SVG_NS) {
            return in_array($el->localName, ['foreignObject', 'desc', 'title'], true);
        }
        return false;
    }

    // ============================================================
    // InTemplate (§13.2.6.4.18)
    // ============================================================
    private function modeInTemplate(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof CharacterToken
            || $token instanceof CommentToken
            || $token instanceof DoctypeToken
        ) {
            $this->modeInBody($token, $tokenizer);
            return;
        }
        if ($token instanceof StartTagToken) {
            $tag = $token->tagName;
            if (in_array($tag, ['base', 'basefont', 'bgsound', 'link', 'meta', 'noframes', 'script', 'style', 'template', 'title'], true)) {
                $this->modeInHead($token, $tokenizer);
                return;
            }
            if (in_array($tag, ['caption', 'colgroup', 'tbody', 'tfoot', 'thead'], true)) {
                array_pop($this->templateInsertionModes);
                $this->templateInsertionModes[] = InsertionMode::InTable;
                $this->insertionMode = InsertionMode::InTable;
                $this->reprocess($token);
                return;
            }
            if ($tag === 'col') {
                array_pop($this->templateInsertionModes);
                $this->templateInsertionModes[] = InsertionMode::InColumnGroup;
                $this->insertionMode = InsertionMode::InColumnGroup;
                $this->reprocess($token);
                return;
            }
            if ($tag === 'tr') {
                array_pop($this->templateInsertionModes);
                $this->templateInsertionModes[] = InsertionMode::InTableBody;
                $this->insertionMode = InsertionMode::InTableBody;
                $this->reprocess($token);
                return;
            }
            if (in_array($tag, ['td', 'th'], true)) {
                array_pop($this->templateInsertionModes);
                $this->templateInsertionModes[] = InsertionMode::InRow;
                $this->insertionMode = InsertionMode::InRow;
                $this->reprocess($token);
                return;
            }
            // Any other start tag.
            array_pop($this->templateInsertionModes);
            $this->templateInsertionModes[] = InsertionMode::InBody;
            $this->insertionMode = InsertionMode::InBody;
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken) {
            if ($token->tagName === 'template') {
                $this->modeInHead($token, $tokenizer);
                return;
            }
            return; // parse error, ignore other end tags
        }
        if ($token instanceof EofToken) {
            if (!$this->openElements->containsLocalName('template')) {
                $this->done = true;
                return;
            }
            // Pop until template popped, clear AFE to marker, pop template
            // insertion mode, reset insertion mode, reprocess.
            $this->openElements->popUntilLocalName('template');
            $this->activeFormatting->clearToLastMarker();
            array_pop($this->templateInsertionModes);
            $this->resetInsertionModeAppropriately();
            $this->reprocess($token);
        }
    }

    // ============================================================
    // InSelect (§13.2.6.4.16)
    // ============================================================
    private function modeInSelect(Token $token): void
    {
        if ($token instanceof CharacterToken) {
            if ($token->data === "\u{0000}") {
                return; // parse error, drop
            }
            $this->insertCharacter($token);
            return;
        }
        if ($token instanceof CommentToken) {
            $this->insertComment($token);
            return;
        }
        if ($token instanceof DoctypeToken) {
            return;
        }
        if ($token instanceof StartTagToken) {
            $tag = $token->tagName;
            if ($tag === 'html') {
                $this->processInBodyForStrayHtml($token);
                return;
            }
            if ($tag === 'option') {
                $current = $this->openElements->currentNode();
                if ($current !== null && $current->localName === 'option') {
                    $this->openElements->pop();
                }
                $this->insertHtmlElement($token);
                return;
            }
            if ($tag === 'optgroup') {
                $current = $this->openElements->currentNode();
                if ($current !== null && $current->localName === 'option') {
                    $this->openElements->pop();
                }
                $current = $this->openElements->currentNode();
                if ($current !== null && $current->localName === 'optgroup') {
                    $this->openElements->pop();
                }
                $this->insertHtmlElement($token);
                return;
            }
            if ($tag === 'select') {
                // Parse error: treat as </select>.
                if (!$this->openElements->hasInSelectScope('select')) {
                    return;
                }
                $this->openElements->popUntilLocalName('select');
                $this->resetInsertionModeAppropriately();
                return;
            }
            if (in_array($tag, ['input', 'keygen', 'textarea'], true)) {
                // Parse error: implicit </select>, then reprocess.
                if (!$this->openElements->hasInSelectScope('select')) {
                    return;
                }
                $this->openElements->popUntilLocalName('select');
                $this->resetInsertionModeAppropriately();
                $this->reprocess($token);
                return;
            }
            if (in_array($tag, ['script', 'template'], true)) {
                $this->modeInHead($token, $this->activeTokenizer ?? new Tokenizer(''));
                return;
            }
            // HTML Living Standard §13.2.6.4.16 — `<hr>` inside a
            // `<select>` pops any open `<option>` / `<optgroup>` and
            // inserts the hr as a void element. Lets authors break
            // an option list into sections.
            if ($tag === 'hr') {
                $current = $this->openElements->currentNode();
                if ($current !== null && $current->localName === 'option') {
                    $this->openElements->pop();
                }
                $current = $this->openElements->currentNode();
                if ($current !== null && $current->localName === 'optgroup') {
                    $this->openElements->pop();
                }
                $this->insertHtmlElement($token);
                // Void element — pop immediately.
                $this->openElements->pop();
                return;
            }
            return; // parse error, ignore other start tags
        }
        if ($token instanceof EndTagToken) {
            $tag = $token->tagName;
            if ($tag === 'optgroup') {
                $items = $this->openElements->items();
                $top = $items[count($items) - 1] ?? null;
                $previous = $items[count($items) - 2] ?? null;
                if ($top !== null && $top->localName === 'option'
                    && $previous !== null && $previous->localName === 'optgroup'
                ) {
                    $this->openElements->pop();
                }
                $current = $this->openElements->currentNode();
                if ($current !== null && $current->localName === 'optgroup') {
                    $this->openElements->pop();
                }
                return;
            }
            if ($tag === 'option') {
                $current = $this->openElements->currentNode();
                if ($current !== null && $current->localName === 'option') {
                    $this->openElements->pop();
                }
                return;
            }
            if ($tag === 'select') {
                if (!$this->openElements->hasInSelectScope('select')) {
                    return; // parse error
                }
                $this->openElements->popUntilLocalName('select');
                $this->resetInsertionModeAppropriately();
                return;
            }
            if ($tag === 'template') {
                $this->modeInHead($token, $this->activeTokenizer ?? new Tokenizer(''));
                return;
            }
            return; // parse error, ignore
        }
        if ($token instanceof EofToken) {
            $this->modeInBody($token, $this->activeTokenizer ?? new Tokenizer(''));
        }
    }

    // ============================================================
    // InSelectInTable (§13.2.6.4.17)
    // ============================================================
    private function modeInSelectInTable(Token $token, Tokenizer $tokenizer): void
    {
        if ($token instanceof StartTagToken
            && in_array($token->tagName, ['caption', 'table', 'tbody', 'tfoot', 'thead', 'tr', 'td', 'th'], true)
        ) {
            // Implicit </select>, then reprocess in surrounding table mode.
            $this->openElements->popUntilLocalName('select');
            $this->resetInsertionModeAppropriately();
            $this->reprocess($token);
            return;
        }
        if ($token instanceof EndTagToken
            && in_array($token->tagName, ['caption', 'table', 'tbody', 'tfoot', 'thead', 'tr', 'td', 'th'], true)
        ) {
            if (!$this->openElements->hasInTableScope($token->tagName)) {
                return; // parse error
            }
            $this->openElements->popUntilLocalName('select');
            $this->resetInsertionModeAppropriately();
            $this->reprocess($token);
            return;
        }
        $this->modeInSelect($token);
    }

    private function closeCell(): void
    {
        $cellName = $this->openElements->hasInTableScope('td') ? 'td' : 'th';
        $this->openElements->generateImpliedEndTags();
        $this->openElements->popUntilLocalName($cellName);
        $this->activeFormatting->clearToLastMarker();
        $this->insertionMode = InsertionMode::InRow;
    }

    // ============================================================
    // Table helpers
    // ============================================================
    private function currentNodeIsTableContext(): bool
    {
        $current = $this->openElements->currentNode();
        if ($current === null || $current->namespaceURI !== Document::HTML_NS) {
            return false;
        }
        return in_array($current->localName, ['table', 'tbody', 'tfoot', 'thead', 'tr'], true);
    }

    private function clearStackToTableContext(): void
    {
        while (true) {
            $current = $this->openElements->currentNode();
            if ($current === null) {
                return;
            }
            if (in_array($current->localName, ['table', 'template', 'html'], true)) {
                return;
            }
            $this->openElements->pop();
        }
    }

    private function clearStackToTableBodyContext(): void
    {
        while (true) {
            $current = $this->openElements->currentNode();
            if ($current === null) {
                return;
            }
            if (in_array($current->localName, ['tbody', 'tfoot', 'thead', 'template', 'html'], true)) {
                return;
            }
            $this->openElements->pop();
        }
    }

    private function clearStackToTableRowContext(): void
    {
        while (true) {
            $current = $this->openElements->currentNode();
            if ($current === null) {
                return;
            }
            if (in_array($current->localName, ['tr', 'template', 'html'], true)) {
                return;
            }
            $this->openElements->pop();
        }
    }

    /**
     * Reset the insertion mode appropriately per §13.2.4.1. Walks the open
     * elements stack from the top down and picks the right mode based on
     * the deepest table-related ancestor (used after `</table>`, `</caption>`,
     * etc. where we exit a table sub-tree).
     */
    private function resetInsertionModeAppropriately(): void
    {
        $items = $this->openElements->items();
        $lastIdx = array_key_last($items);
        for ($i = $lastIdx; $i !== null && $i >= 0; $i--) {
            $node = $items[$i];
            $name = $node->localName;
            // Phase 1B.3 simplification: ignore the "last" flag from the
            // fragment-parsing case (no fragment parsing yet).
            if ($name === 'select') {
                $this->insertionMode = InsertionMode::InSelect;
                return;
            }
            if (in_array($name, ['td', 'th'], true) && $i !== 0) {
                $this->insertionMode = InsertionMode::InCell;
                return;
            }
            if ($name === 'tr') {
                $this->insertionMode = InsertionMode::InRow;
                return;
            }
            if (in_array($name, ['tbody', 'thead', 'tfoot'], true)) {
                $this->insertionMode = InsertionMode::InTableBody;
                return;
            }
            if ($name === 'caption') {
                $this->insertionMode = InsertionMode::InCaption;
                return;
            }
            if ($name === 'colgroup') {
                $this->insertionMode = InsertionMode::InColumnGroup;
                return;
            }
            if ($name === 'table') {
                $this->insertionMode = InsertionMode::InTable;
                return;
            }
            if ($name === 'template') {
                $top = $this->templateInsertionModes[count($this->templateInsertionModes) - 1] ?? null;
                $this->insertionMode = $top ?? InsertionMode::InTemplate;
                return;
            }
            if ($name === 'head' && $i !== 0) {
                $this->insertionMode = InsertionMode::InHead;
                return;
            }
            if ($name === 'body') {
                $this->insertionMode = InsertionMode::InBody;
                return;
            }
            if ($name === 'frameset') {
                $this->insertionMode = InsertionMode::InFrameset;
                return;
            }
            if ($name === 'html') {
                $this->insertionMode = $this->headElement === null
                    ? InsertionMode::BeforeHead
                    : InsertionMode::AfterHead;
                return;
            }
        }
        $this->insertionMode = InsertionMode::InBody;
    }

    // ============================================================
    // Helpers
    // ============================================================
    private function isWhitespaceOnlyCharacter(Token $token): bool
    {
        if (!$token instanceof CharacterToken) {
            return false;
        }
        return preg_match('/^[\t\n\f\r ]+$/', $token->data) === 1;
    }

    private function reprocess(Token $token): void
    {
        // Reprocess via the same active tokenizer so any state mutation
        // (RCDATA/RAWTEXT/ScriptData switches in InHead) takes effect on the
        // real stream rather than a throwaway instance.
        if ($this->activeTokenizer === null) {
            throw new \LogicException('reprocess called outside build()');
        }
        $this->dispatch($token, $this->activeTokenizer);
    }

    /**
     * Resolve the `shadowrootmode` attribute on a `<template>` start tag into
     * a typed mode, or null if absent / invalid. The spec says only "open" and
     * "closed" are accepted; any other value is a missing-value state.
     */
    private function resolveShadowRootMode(StartTagToken $token): ?ShadowRootMode
    {
        foreach ($token->attributes as $attr) {
            if ($attr['name'] === 'shadowrootmode') {
                return match (strtolower($attr['value'])) {
                    'open' => ShadowRootMode::Open,
                    'closed' => ShadowRootMode::Closed,
                    default => null,
                };
            }
        }
        return null;
    }

    private function tokenHasAttribute(StartTagToken $token, string $name): bool
    {
        foreach ($token->attributes as $attr) {
            if ($attr['name'] === $name) {
                return true;
            }
        }
        return false;
    }

}
