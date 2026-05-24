<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

/**
 * Base type for tokens emitted by the WHATWG tokenizer.
 *
 * Tokens are mutable while the tokenizer is building them (start tag attribute
 * lists accumulate character by character, for example). Once emitted to the
 * tree-construction stage they are conceptually frozen, but the type system
 * does not enforce immutability — keep the lifetime short and pass through.
 */
abstract class Token {}
