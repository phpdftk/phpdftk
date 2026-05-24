<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/**
 * Base type for CSS Syntax Module 3 tokens. Per the spec the tokenizer
 * emits a stream of typed tokens; each concrete subclass corresponds to
 * one of the token kinds enumerated in §4.
 */
abstract readonly class Token {}
