<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Selector\MatchableElement;

/**
 * In-test stub implementation of {@see MatchableElement} for unit
 * tests of the selector engine + cascade. Builds tiny element
 * trees via `appendFake`. Lives in its own file (PSR-4
 * autoloaded) so any test file can use it without needing to
 * `require_once` MatcherTest.php.
 */
final class FakeElement implements MatchableElement
{
    public ?FakeElement $parent = null;
    /** @var list<FakeElement> */
    public array $childrenList = [];

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string $tag,
        public ?string $id = null,
        public array $classes = [],
        public array $attributes = [],
        public ?string $namespace = null,
    ) {}

    public function appendFake(FakeElement $child): void
    {
        $child->parent = $this;
        $this->childrenList[] = $child;
    }

    public function localName(): string
    {
        return $this->tag;
    }

    public function namespaceUri(): ?string
    {
        return $this->namespace;
    }

    public function elementId(): ?string
    {
        return $this->id;
    }

    public function classes(): array
    {
        return $this->classes;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function getAttributeValue(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function allAttributes(): array
    {
        return $this->attributes;
    }

    public function parentElement(): ?MatchableElement
    {
        return $this->parent;
    }

    public function previousElementSibling(): ?MatchableElement
    {
        if ($this->parent === null) {
            return null;
        }
        $prev = null;
        foreach ($this->parent->childrenList as $c) {
            if ($c === $this) {
                return $prev;
            }
            $prev = $c;
        }
        return null;
    }

    public function nextElementSibling(): ?MatchableElement
    {
        if ($this->parent === null) {
            return null;
        }
        $found = false;
        foreach ($this->parent->childrenList as $c) {
            if ($found) {
                return $c;
            }
            if ($c === $this) {
                $found = true;
            }
        }
        return null;
    }

    public function elementChildren(): array
    {
        return $this->childrenList;
    }

    public function indexAmongSiblings(): int
    {
        if ($this->parent === null) {
            return 1;
        }
        foreach ($this->parent->childrenList as $i => $c) {
            if ($c === $this) {
                return $i + 1;
            }
        }
        return 1;
    }

    public function indexAmongSiblingsFromEnd(): int
    {
        if ($this->parent === null) {
            return 1;
        }
        $total = count($this->parent->childrenList);
        foreach ($this->parent->childrenList as $i => $c) {
            if ($c === $this) {
                return $total - $i;
            }
        }
        return 1;
    }

    public function indexAmongTypeSiblings(): int
    {
        if ($this->parent === null) {
            return 1;
        }
        $i = 0;
        foreach ($this->parent->childrenList as $c) {
            if ($c->tag === $this->tag && $c->namespace === $this->namespace) {
                $i++;
            }
            if ($c === $this) {
                return $i;
            }
        }
        return 1;
    }

    public function indexAmongTypeSiblingsFromEnd(): int
    {
        if ($this->parent === null) {
            return 1;
        }
        $count = 0;
        foreach ($this->parent->childrenList as $c) {
            if ($c->tag === $this->tag && $c->namespace === $this->namespace) {
                $count++;
            }
        }
        $rank = 0;
        foreach ($this->parent->childrenList as $c) {
            if ($c->tag === $this->tag && $c->namespace === $this->namespace) {
                $rank++;
            }
            if ($c === $this) {
                return $count - $rank + 1;
            }
        }
        return 1;
    }
}
