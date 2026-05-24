<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

/**
 * Severity tier for renderer {@see Warning}s.
 *
 * - `Info` — informational; the renderer made a guess but the output is
 *   probably still correct (e.g. fell back to default font).
 * - `Warning` — visual gap likely (unsupported property silently dropped).
 * - `Error` — content lost or definitely-wrong output; promotes to throw
 *   under {@see RendererOptions::$strict}.
 */
enum WarningSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
