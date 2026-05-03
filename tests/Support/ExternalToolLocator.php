<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Support;

final class ExternalToolLocator
{
    /** @var array<string, ?string> */
    private static array $cache = [];

    /**
     * @param string[] $extraPaths
     */
    public static function find(string $binary, array $extraPaths = []): ?string
    {
        if (array_key_exists($binary, self::$cache)) {
            return self::$cache[$binary];
        }

        foreach ($extraPaths as $path) {
            if (is_executable($path)) {
                return self::$cache[$binary] = $path;
            }
        }

        $which = trim((string) @shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));

        return self::$cache[$binary] = ($which !== '' ? $which : null);
    }
}
