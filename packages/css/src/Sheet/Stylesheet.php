<?php

declare(strict_types=1);

namespace Phpdftk\Css\Sheet;

/**
 * A parsed CSS stylesheet — a sequence of {@see Rule}s plus a cascade
 * {@see Origin}. The Origin determines precedence at cascade time per
 * CSS Cascade 5 §6.
 */
final readonly class Stylesheet
{
    /** @param list<Rule> $rules */
    public function __construct(
        public array $rules,
        public Origin $origin = Origin::Author,
    ) {}
}
