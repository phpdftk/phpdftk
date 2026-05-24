<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * The `<template>` element. Per WHATWG DOM, a template has a `content` IDL
 * attribute pointing to a DocumentFragment that holds its parsed children;
 * the children are NOT inserted into the template element itself.
 *
 * For Declarative Shadow DOM (`<template shadowrootmode>`), the parser
 * attaches a shadow root to the *parent* element and sets this template's
 * `content` to that shadow root, so inserted children flow into the shadow
 * tree. The `isDeclarativeShadowRoot` flag tells the parser to remove the
 * template element from the light DOM once its tag closes — the shadow root
 * on the parent is the surviving artefact.
 *
 * Phase 1B.4 ships the structural integration. The full DSD encapsulation
 * (slot distribution, shadow-scoped selectors, `:host`/`::slotted`/`::part`)
 * lands when `phpdftk/css` arrives (Phase 1D).
 */
final class HTMLTemplateElement extends Element
{
    public ?DocumentFragment $content = null;
    public bool $isDeclarativeShadowRoot = false;
}
