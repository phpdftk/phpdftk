<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Result;

enum ViolationSeverity: string
{
    /** Hard failure — the document does not conform. */
    case Error = 'error';

    /** Advisory — potential conformance issue that may pass some validators. */
    case Warning = 'warning';
}
