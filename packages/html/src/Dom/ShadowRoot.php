<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Shadow root attached to an Element host. Created by the parser when it
 * encounters <template shadowrootmode="open|closed"> (declarative shadow DOM)
 * or by Element::attachShadow() at author request.
 *
 * Inherits DocumentFragment so it composes into the tree the same way and
 * carries no host-document state of its own.
 */
final class ShadowRoot extends DocumentFragment
{
    public readonly Element $host;
    public readonly ShadowRootMode $mode;
    public readonly bool $delegatesFocus;
    public readonly bool $clonable;
    public readonly bool $serializable;
    public readonly SlotAssignment $slotAssignment;

    public function __construct(
        Element $host,
        ShadowRootMode $mode,
        ShadowRootInit $init = new ShadowRootInit(),
    ) {
        parent::__construct($host->ownerDocument);
        $this->host = $host;
        $this->mode = $mode;
        $this->delegatesFocus = $init->delegatesFocus;
        $this->clonable = $init->clonable;
        $this->serializable = $init->serializable;
        $this->slotAssignment = $init->slotAssignment;
    }

    /**
     * Slots in tree order. Walks the entire shadow tree, depth-first.
     *
     * @return list<HTMLSlotElement>
     */
    public function slots(): array
    {
        $out = [];
        $this->collectSlots($this, $out);
        return $out;
    }

    /** @param list<HTMLSlotElement> $out */
    private function collectSlots(Node $scope, array &$out): void
    {
        for ($n = $scope->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof HTMLSlotElement) {
                $out[] = $n;
            }
            if ($n->hasChildNodes()) {
                $this->collectSlots($n, $out);
            }
        }
    }

    protected function shallowClone(): static
    {
        throw new \LogicException(
            'ShadowRoot::shallowClone requires a host context; clone via Element::cloneNode(deep: true) ' .
            'which delegates to the host clone path.',
        );
    }
}
