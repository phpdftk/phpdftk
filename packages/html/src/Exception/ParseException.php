<?php

declare(strict_types=1);

namespace Phpdftk\Html\Exception;

/**
 * Raised when input HTML cannot be parsed. WHATWG mandates that the HTML
 * parser never fails on real input — every byte sequence is parsed into some
 * DOM, possibly via error-recovery rules. This exception is reserved for
 * I/O-level failures (e.g. invalid encoding declaration) where recovery is
 * not meaningful.
 */
final class ParseException extends HtmlException {}
