<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Discriminator on {@see TargetFunction} — which CSS Generated
 * Content for Paged Media 3 §3 cross-reference variant.
 */
enum TargetFunctionKind: string
{
    case Counter = 'target-counter';
    case Counters = 'target-counters';
    case Text = 'target-text';
}
