<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

interface Serializable
{
    public function toPdf(): string;
}
