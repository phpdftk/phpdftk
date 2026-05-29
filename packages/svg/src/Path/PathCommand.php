<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/**
 * One step in a parsed SVG path-data attribute (SVG 2 §9.3.9). The set of
 * implementations is closed — MoveTo / LineTo / HorizontalLineTo /
 * VerticalLineTo / CurveTo / SmoothCurveTo / QuadraticCurveTo /
 * SmoothQuadraticCurveTo / ArcTo / ClosePath. The painter pattern-matches
 * on the concrete type to emit PDF operators.
 *
 * `$absolute = true` means the command used the uppercase letter (`M`, `L`,
 * etc.); `false` means lowercase. For `ClosePath` (`Z`/`z`) the field is
 * preserved for round-tripping but the spec defines no semantic difference.
 */
interface PathCommand
{
    public bool $absolute { get; }
}
