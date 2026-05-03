<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Support;

final readonly class DockerToolResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}
}
