<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Thrown when a non-forgiving selector context encounters a syntax error
 * (e.g. inside `:not()` or `:has()`). Forgiving contexts (`:is()`,
 * `:where()`, top-level selector lists) catch this and drop the offending
 * selector per Selectors 4 §3.7.
 */
final class SelectorSyntaxException extends \RuntimeException {}
