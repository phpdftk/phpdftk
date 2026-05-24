<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Optional initialisation data for Element::attachShadow().
 */
final readonly class ShadowRootInit
{
    public function __construct(
        public bool $delegatesFocus = false,
        public bool $clonable = false,
        public bool $serializable = false,
        public SlotAssignment $slotAssignment = SlotAssignment::Named,
    ) {}
}
