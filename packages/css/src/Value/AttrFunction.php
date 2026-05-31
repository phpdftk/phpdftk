<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `attr(<attr-name> [<type-or-unit>], <fallback>?)` per CSS Values
 * 5 §11. Resolves to the named attribute's value at computed-value
 * time, optionally coerced into the declared type and falling back
 * to the supplied default when the attribute is missing.
 *
 * Modern syntax (`attr(<attr-name> type(<syntax>), <fallback>)`)
 * stores the syntax string verbatim in `$typeOrUnit`; the cascade
 * handles either form.
 *
 *   content: attr(data-name);
 *   content: attr(data-name string);
 *   content: attr(data-name string, "(none)");
 *   width:   attr(data-w px, 100px);
 *   color:   attr(data-c color, currentcolor);
 */
final readonly class AttrFunction extends Value
{
    public function __construct(
        public string $attributeName,
        /**
         * Optional type/unit hint. One of CSS Values 5's named
         * types (`string`, `number`, `integer`, `length`, `angle`,
         * `time`, `frequency`, `percentage`, `color`, `url`, `ident`)
         * or a CSS unit token (`px`, `em`, etc.) — stored verbatim.
         * Null when the author wrote the bare attr(<name>) form.
         */
        public ?string $typeOrUnit = null,
        /**
         * Optional fallback expression when the attribute is
         * missing or its value can't be coerced into the declared
         * type.
         */
        public ?Value $fallback = null,
    ) {}

    public function toCss(): string
    {
        $parts = [$this->attributeName];
        if ($this->typeOrUnit !== null) {
            $parts[] = $this->typeOrUnit;
        }
        $head = 'attr(' . implode(' ', $parts);
        return $this->fallback !== null
            ? $head . ', ' . $this->fallback->toCss() . ')'
            : $head . ')';
    }
}
