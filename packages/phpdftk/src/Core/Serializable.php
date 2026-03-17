<?php

declare(strict_types=1);

namespace Phpdftk\Core;

interface Serializable
{
    public function toPdf(): string;
}
