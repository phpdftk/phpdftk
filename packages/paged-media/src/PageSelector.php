<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia;

/**
 * CSS Paged Media 3 §3.1 — `@page` pseudo-class selectors.
 *
 * A `@page :first` rule applies to the first page of a named-page
 * group. `@page :left` / `@page :right` alternate for two-sided
 * print. `@page :blank` matches pages forced empty by a `break-
 * before: page` directive.
 *
 * CSS Paged Media 3 §3.1.5 — `:nth(an+b)` for arbitrary page indices.
 *
 * Phase 4G.1 (extraction) maps the at-rule prelude text (`:first`,
 * `:left`, `:nth(2n+1)`, …) into the enum cases. `:nth(an+b)` is
 * the only one that takes parameters and is represented by a
 * separate value type (`NthSelector`) rather than an enum case.
 */
enum PageSelector: string
{
    case First = 'first';
    case Left = 'left';
    case Right = 'right';
    case Blank = 'blank';
}
