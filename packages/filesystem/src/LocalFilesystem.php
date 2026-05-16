<?php

declare(strict_types=1);

namespace Phpdftk\Filesystem;

final class LocalFilesystem
{
    public static function readFile(string $path, string $label = 'file'): string
    {
        self::assertReadableFile($path, $label);

        $bytes = file_get_contents($path);
        // @codeCoverageIgnoreStart
        // Defensive: assertReadableFile validated is_file + is_readable; this
        // branch fires only on a race (file deleted/permissions revoked between
        // the check and the read) or a transient I/O error.
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read $label: $path");
        }
        // @codeCoverageIgnoreEnd

        return $bytes;
    }

    public static function readPrefix(string $path, int $length, string $label = 'file'): string
    {
        self::assertReadableFile($path, $label);

        $bytes = file_get_contents($path, false, null, 0, $length);
        // @codeCoverageIgnoreStart
        // Defensive: see readFile().
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read $label: $path");
        }
        // @codeCoverageIgnoreEnd

        return $bytes;
    }

    /** @return resource */
    public static function openReadable(string $path, string $label = 'file')
    {
        self::assertReadableFile($path, $label);

        $handle = fopen($path, 'rb');
        // @codeCoverageIgnoreStart
        // Defensive: assertReadableFile validated is_file + is_readable.
        if ($handle === false) {
            throw new \RuntimeException("Cannot open $label: $path");
        }
        // @codeCoverageIgnoreEnd

        return $handle;
    }

    public static function writeFile(string $path, string $bytes, bool $createDirectories = false): void
    {
        self::assertLocalPath($path);

        $dir = dirname($path);
        if (!is_dir($dir) && $createDirectories && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Directory does not exist: $dir");
        }

        if (!is_dir($dir)) {
            throw new \RuntimeException("Directory does not exist: $dir");
        }

        if (file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException("Cannot write file: $path");
        }
    }

    public static function assertReadableFile(string $path, string $label = 'file'): void
    {
        self::assertLocalPath($path);

        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Cannot read $label: $path");
        }
    }

    public static function assertLocalPath(string $path): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\\/\\//', $path) === 1) {
            throw new \InvalidArgumentException("Stream wrappers are not allowed for file paths: $path");
        }
    }
}
