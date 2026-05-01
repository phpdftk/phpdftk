<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support;

final class DockerToolRunner
{
    private static ?bool $dockerAvailable = null;

    /** @var array<string, bool> */
    private static array $imageCache = [];

    public static function isAvailable(): bool
    {
        if (self::$dockerAvailable !== null) {
            return self::$dockerAvailable;
        }

        exec('docker info 2>/dev/null', $output, $ret);

        return self::$dockerAvailable = ($ret === 0);
    }

    public static function hasImage(string $image): bool
    {
        if (array_key_exists($image, self::$imageCache)) {
            return self::$imageCache[$image];
        }

        exec(
            sprintf('docker image inspect %s 2>/dev/null', escapeshellarg($image)),
            $output,
            $ret,
        );

        return self::$imageCache[$image] = ($ret === 0);
    }

    /**
     * Check if a host path is in a directory that Docker Desktop can mount.
     *
     * On macOS, Docker Desktop can mount /Users but not arbitrary system
     * temp directories like /private/var/folders/.
     */
    public static function isPathMountable(string $path): bool
    {
        $real = realpath($path) ?: $path;

        // On macOS, /Users is always shared; system temp dirs are not
        if (PHP_OS_FAMILY === 'Darwin') {
            return str_starts_with($real, '/Users/');
        }

        // On Linux, Docker can mount anything
        return true;
    }

    /**
     * Return a temp directory suitable for Docker volume mounting.
     *
     * When Docker is available, uses a project-relative directory that Docker
     * can always mount. Falls back to the system temp directory otherwise.
     */
    public static function tempDir(): string
    {
        if (self::isAvailable()) {
            $dir = dirname(__DIR__) . '/.tmp';
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            return $dir;
        }

        return sys_get_temp_dir();
    }

    /**
     * Run a command inside a Docker container with the given volume mounted at /data.
     *
     * @param string   $image      Docker image name (e.g., 'phpdftk/qpdf')
     * @param string[] $args       Command arguments passed to the container entrypoint
     * @param string   $volumePath Host directory to mount as /data
     */
    public static function run(string $image, array $args, string $volumePath): DockerToolResult
    {
        $cmd = sprintf(
            'docker run --rm -v %s:/data %s %s 2>&1',
            escapeshellarg($volumePath),
            escapeshellarg($image),
            implode(' ', array_map(escapeshellarg(...), $args)),
        );

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);

        return new DockerToolResult($ret, implode("\n", $output));
    }
}
