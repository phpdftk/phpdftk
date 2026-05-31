<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Base class for the CSS Shapes 1 §3 basic-shape value types —
 * `inset()`, `circle()`, `ellipse()`, `polygon()`, `path()`,
 * `rect()`, `xywh()`. Used as values for `clip-path`,
 * `shape-outside`, `offset-path`.
 *
 * Concrete subclasses store the geometry parameters; the painter
 * dispatches by class on cmd.
 */
abstract readonly class BasicShape extends Value {}
