<?php

declare(strict_types=1);

namespace Phpdftk\Css\Sheet;

/**
 * Cascade origin per CSS Cascade and Inheritance Module 5 §6. Determines
 * the precedence band a stylesheet's rules participate in: UA stylesheets
 * lose to author stylesheets, which lose to user stylesheets *with*
 * `!important`, etc.
 */
enum Origin
{
    case UserAgent;
    case User;
    case Author;
}
