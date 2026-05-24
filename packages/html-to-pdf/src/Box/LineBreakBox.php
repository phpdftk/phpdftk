<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * Sentinel inline box marking an HTML `<br>` element — a mandatory line
 * break inside the inline formatting context. Carries no width and no
 * children; the inline layout reads it as a hard break that survives
 * `white-space: normal`'s collapsing rules.
 */
final class LineBreakBox extends Box {}
