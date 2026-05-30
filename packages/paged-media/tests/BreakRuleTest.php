<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia\Tests;

use Phpdftk\PagedMedia\Fragmentation\BreakRule;
use PHPUnit\Framework\TestCase;

final class BreakRuleTest extends TestCase
{
    public function testForcedValuesAreClassifiedCorrectly(): void
    {
        foreach ([
            BreakRule::Always,
            BreakRule::Page,
            BreakRule::Left,
            BreakRule::Right,
            BreakRule::Recto,
            BreakRule::Verso,
            BreakRule::Column,
        ] as $rule) {
            self::assertTrue($rule->isForced(), "$rule->value should be forced");
            self::assertFalse($rule->isAvoid(), "$rule->value should not be avoid");
        }
    }

    public function testAvoidValuesAreClassifiedCorrectly(): void
    {
        foreach ([BreakRule::Avoid, BreakRule::AvoidPage, BreakRule::AvoidColumn] as $rule) {
            self::assertTrue($rule->isAvoid(), "$rule->value should be avoid");
            self::assertFalse($rule->isForced(), "$rule->value should not be forced");
        }
    }

    public function testAutoIsNeitherForcedNorAvoid(): void
    {
        self::assertFalse(BreakRule::Auto->isForced());
        self::assertFalse(BreakRule::Auto->isAvoid());
    }
}
