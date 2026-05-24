<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * The <slot> element. Acts as a placeholder in a shadow tree that gets filled
 * by light-DOM children of the host during flat-tree composition.
 *
 * The parser creates instances of this class rather than the generic Element
 * for the "slot" tag in the HTML namespace, so layout code can rely on
 * `instanceof HTMLSlotElement` instead of name-matching strings.
 */
final class HTMLSlotElement extends Element
{
    public ?string $name {
        get => $this->getAttribute('name');
    }

    /**
     * The light-DOM nodes assigned to this slot. Populated by the slot
     * distribution algorithm during flat-tree composition; empty until
     * composition runs.
     *
     * For Phase 1B this returns the manual-assignment list set via
     * setManuallyAssignedNodes(). The Named-mode auto-assignment that scans
     * the host's children for slot="name" matches lives in the layout
     * engine (Phase 1E/1F) where the flat tree is composed.
     *
     * @return list<Node>
     */
    public function assignedNodes(bool $flatten = false): array
    {
        $nodes = $this->manuallyAssigned;
        if (!$flatten) {
            return $nodes;
        }
        // Flatten: replace any nested slots with their own assigned nodes.
        $out = [];
        foreach ($nodes as $n) {
            if ($n instanceof self) {
                foreach ($n->assignedNodes(true) as $inner) {
                    $out[] = $inner;
                }
            } else {
                $out[] = $n;
            }
        }
        return $out;
    }

    /** @return list<Element> assignedNodes filtered to Elements */
    public function assignedElements(bool $flatten = false): array
    {
        return array_values(array_filter(
            $this->assignedNodes($flatten),
            static fn(Node $n): bool => $n instanceof Element,
        ));
    }

    /**
     * For manual slot assignment (SlotAssignment::Manual). Phase 1E's flat-
     * tree composer writes the named-mode assignments through the same
     * setter; the parser never calls it directly.
     *
     * @param list<Node> $nodes
     */
    public function setAssignedNodes(array $nodes): void
    {
        $this->manuallyAssigned = $nodes;
    }

    /** @var list<Node> */
    private array $manuallyAssigned = [];
}
