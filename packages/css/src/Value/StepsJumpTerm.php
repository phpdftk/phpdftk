<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Jump-term modifier on {@see StepsEasing}. CSS Easing 1 §3.5.
 *
 *   start       jump on the first step (legacy `step-start`)
 *   end         jump on the last step (default, legacy `step-end`)
 *   jump-start  alias for `start`
 *   jump-end    alias for `end`
 *   jump-none   no jumps at boundaries; N steps = N-1 transitions
 *   jump-both   jumps at both boundaries; N steps = N+1 transitions
 */
enum StepsJumpTerm: string
{
    case Start = 'start';
    case End = 'end';
    case JumpStart = 'jump-start';
    case JumpEnd = 'jump-end';
    case JumpNone = 'jump-none';
    case JumpBoth = 'jump-both';
}
