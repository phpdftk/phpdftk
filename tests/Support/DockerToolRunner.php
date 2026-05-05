<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Support;

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

    /**
     * Start a persistent container with a volume mounted at /data.
     *
     * Returns the container ID. Use exec() to run commands inside it,
     * then stop() when done. Much faster than run() for many invocations
     * since the container (and JVM, etc.) only starts once.
     *
     * @param string   $image      Docker image name
     * @param string   $volumePath Host directory to mount as /data
     * @param string   $name       Optional container name for identification
     */
    public static function start(string $image, string $volumePath, string $name = ''): ?string
    {
        // Remove any leftover container with the same name
        if ($name !== '') {
            exec(sprintf('docker rm -f %s 2>/dev/null', escapeshellarg($name)));
        }

        $nameArg = $name !== '' ? sprintf('--name %s', escapeshellarg($name)) : '';
        $cmd = sprintf(
            'docker run -d %s --entrypoint "" -v %s:/data %s tail -f /dev/null 2>/dev/null',
            $nameArg,
            escapeshellarg($volumePath),
            escapeshellarg($image),
        );

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);

        if ($ret !== 0 || $output === []) {
            return null;
        }

        // Container ID is the last line of output
        return trim(end($output));
    }

    /**
     * Execute a command inside a running container.
     *
     * @param string   $containerId Container ID or name
     * @param string[] $args        Command and arguments to execute
     */
    public static function exec(string $containerId, array $args): DockerToolResult
    {
        $cmd = sprintf(
            'docker exec %s %s 2>&1',
            escapeshellarg($containerId),
            implode(' ', array_map(escapeshellarg(...), $args)),
        );

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);

        return new DockerToolResult($ret, implode("\n", $output));
    }

    /**
     * Stop and remove a running container.
     */
    public static function stop(string $containerId): void
    {
        exec(sprintf('docker rm -f %s 2>/dev/null', escapeshellarg($containerId)));
    }
}
