<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Live token list backed by an Element's `class` attribute. Per WHATWG DOM
 * §7.1, mutations write back through to the underlying `class` attribute.
 *
 * Order-preserving and deduplicating: the underlying class attribute is
 * canonicalised to a single-space-separated, dedup'd list on every mutation.
 */
final class ClassList
{
    public function __construct(private Element $owner) {}

    public function contains(string $token): bool
    {
        return in_array($token, $this->tokens(), true);
    }

    public function add(string ...$tokens): void
    {
        $current = $this->tokens();
        foreach ($tokens as $t) {
            self::assertValidToken($t);
            if (!in_array($t, $current, true)) {
                $current[] = $t;
            }
        }
        $this->write($current);
    }

    public function remove(string ...$tokens): void
    {
        $current = $this->tokens();
        foreach ($tokens as $t) {
            self::assertValidToken($t);
        }
        $current = array_values(array_filter($current, fn(string $c): bool => !in_array($c, $tokens, true)));
        $this->write($current);
    }

    public function toggle(string $token, ?bool $force = null): bool
    {
        self::assertValidToken($token);
        $present = $this->contains($token);
        $shouldBePresent = $force ?? !$present;
        if ($shouldBePresent && !$present) {
            $this->add($token);
        } elseif (!$shouldBePresent && $present) {
            $this->remove($token);
        }
        return $shouldBePresent;
    }

    /** @return list<string> */
    public function values(): array
    {
        return $this->tokens();
    }

    public function count(): int
    {
        return count($this->tokens());
    }

    /** @return list<string> */
    private function tokens(): array
    {
        $raw = $this->owner->getAttribute('class') ?? '';
        $tokens = preg_split('/\s+/', trim($raw)) ?: [];
        return array_values(array_unique(array_filter($tokens, static fn(string $t): bool => $t !== '')));
    }

    /** @param list<string> $tokens */
    private function write(array $tokens): void
    {
        $this->owner->setAttribute('class', implode(' ', $tokens));
    }

    private static function assertValidToken(string $token): void
    {
        if ($token === '') {
            throw new \InvalidArgumentException('Class token may not be empty');
        }
        if (preg_match('/\s/', $token)) {
            throw new \InvalidArgumentException('Class token may not contain whitespace: ' . $token);
        }
    }
}
