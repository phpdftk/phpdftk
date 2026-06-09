<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\BrowserOracle;
use PHPUnit\Framework\TestCase;

/**
 * Daemon-mode coverage for {@see BrowserOracle}. Spins up the mock
 * daemon at fixtures/mock-daemon.php behind the PHP built-in HTTP
 * server, then drives the oracle through `isAvailable()` /
 * `render()` while flipping the daemon's response shape via a state
 * file.
 *
 * Negative cases lead — every connection failure, every malformed
 * response, every error code the daemon could return needs to land
 * cleanly because the oracle is invoked tens of thousands of times
 * during a corpus sweep and one bad response shouldn't poison the
 * whole run.
 */
final class BrowserOracleDaemonTest extends TestCase
{
    private static ?string $serverPid = null;

    /** Port for the chromium-engine mock (port-offset 1 → 9101 mapping). */
    private static int $port = 0;

    /** Base URL fed to BrowserOracle — engine port appended at dispatch time. */
    private static string $daemonBase = '';

    private static string $stateFile = '';

    private static string $hostWptRoot = '';

    private static string $cacheDir = '';

    public static function setUpBeforeClass(): void
    {
        self::$stateFile = sys_get_temp_dir() . '/browser-oracle-daemon-state-' . bin2hex(random_bytes(4)) . '.json';
        self::$hostWptRoot = sys_get_temp_dir() . '/browser-oracle-wpt-' . bin2hex(random_bytes(4));
        self::$cacheDir = sys_get_temp_dir() . '/browser-oracle-cache-' . bin2hex(random_bytes(4));
        mkdir(self::$hostWptRoot . '/css', recursive: true);
        mkdir(self::$cacheDir, recursive: true);
        file_put_contents(self::$hostWptRoot . '/css/flex.html', '<html></html>');

        // The oracle composes URLs as `${daemonBase}${engineOffset}` —
        // chromium=1, firefox=2, webkit=3. So if we want the mock to
        // receive chromium dispatches on port P, P must end in 1 and
        // the base URL becomes everything-but-the-last-digit of P.
        // Probe a fixed band of ports ending in 1 until one binds.
        $port = self::findFreePortEndingIn(1);
        self::$port = $port;
        self::$daemonBase = 'http://127.0.0.1:' . substr((string) $port, 0, -1);

        $mockScript = realpath(__DIR__ . '/fixtures/mock-daemon.php');
        $env = ['MOCK_DAEMON_STATE' => self::$stateFile, 'PATH' => getenv('PATH')];
        $cmd = ['php', '-S', '127.0.0.1:' . $port, $mockScript];
        $proc = proc_open(
            $cmd,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            $env,
        );
        if (!is_resource($proc)) {
            self::fail('failed to launch mock daemon');
        }
        $status = proc_get_status($proc);
        self::$serverPid = (string) $status['pid'];

        // Wait for the server to come up — poll the TCP socket directly
        // because /status would need a state file we haven't written yet.
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($fp !== false) {
                fclose($fp);
                return;
            }
            usleep(50_000);
        }
        self::fail('mock daemon did not bind within 5s');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverPid !== null) {
            // proc_terminate would only kill the shell wrapper on some
            // systems — go through SIGTERM on the actual PID.
            posix_kill((int) self::$serverPid, SIGTERM);
        }
        @unlink(self::$stateFile);
        self::rmrf(self::$hostWptRoot);
        self::rmrf(self::$cacheDir);
    }

    protected function tearDown(): void
    {
        @unlink(self::$stateFile);
    }

    // -----------------------------------------------------------------
    // isAvailable()
    // -----------------------------------------------------------------

    public function testIsAvailableReturnsFalseWhenDaemonIsDown(): void
    {
        // Point at a port nothing listens on. Connect should fail fast.
        $oracle = new BrowserOracle(daemonBase: 'http://127.0.0.1:6553');
        self::assertFalse($oracle->isAvailable('chromium'));
    }

    public function testIsAvailableReturnsFalseWhenDaemonReports500(): void
    {
        $this->writeState(['status' => ['code' => 500, 'body' => ['error' => 'boom']]]);
        $oracle = $this->makeOracle();
        self::assertFalse($oracle->isAvailable('chromium'));
    }

    public function testIsAvailableReturnsFalseWhenDaemonReportsReadyFalse(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => false]],
        ]);
        $oracle = $this->makeOracle();
        self::assertFalse($oracle->isAvailable('chromium'));
    }

    public function testIsAvailableReturnsFalseWhenDaemonReturnsNonJson(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'raw' => true, 'body' => 'not json at all', 'content_type' => 'text/plain'],
        ]);
        $oracle = $this->makeOracle();
        self::assertFalse($oracle->isAvailable('chromium'));
    }

    public function testIsAvailableReturnsFalseForUnmappedEngine(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
        ]);
        $oracle = $this->makeOracle();
        // 'opera' isn't in ENGINE_PORTS — must return false rather than
        // crashing on a null URL.
        self::assertFalse($oracle->isAvailable('opera'));
    }

    public function testIsAvailableReturnsTrueWhenDaemonIsReady(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
        ]);
        $oracle = $this->makeOracle();
        self::assertTrue($oracle->isAvailable('chromium'));
    }

    // -----------------------------------------------------------------
    // render() — dispatch path
    // -----------------------------------------------------------------

    public function testRenderThrowsWhenDaemonReturns500(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => ['code' => 500, 'body' => ['error' => 'browser crashed']],
        ]);
        $oracle = $this->makeOracle();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');
        $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
    }

    public function testRenderThrowsWhenDaemonReturns400(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => ['code' => 400, 'body' => ['error' => 'bad fixture path']],
        ]);
        $oracle = $this->makeOracle();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 400/');
        $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
    }

    public function testRenderThrowsWhenDaemonReturnsNonJson(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => ['code' => 200, 'raw' => true, 'body' => '<html>not pdf</html>', 'content_type' => 'text/html'],
        ]);
        $oracle = $this->makeOracle();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/non-JSON/');
        $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
    }

    public function testRenderThrowsWhenDaemonReturnsNoPdfField(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => ['code' => 200, 'body' => ['ms' => 10]],
        ]);
        $oracle = $this->makeOracle();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no PDF bytes/');
        $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
    }

    public function testRenderThrowsWhenDaemonReturnsMalformedBase64(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => ['code' => 200, 'body' => ['pdf_bytes_base64' => '!!!not-base64!!!', 'ms' => 1, 'from_cache' => false]],
        ]);
        $oracle = $this->makeOracle();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/malformed base64/');
        $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
    }

    public function testRenderThrowsForUnsupportedEngine(): void
    {
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
        ]);
        $oracle = $this->makeOracle();
        // 'opera' isn't in ENGINE_PORTS. isAvailable returns false →
        // render returns null without dispatching.
        self::assertNull($oracle->render('opera', self::$hostWptRoot . '/css/flex.html'));
    }

    public function testRenderWritesPdfBytesOnHappyPath(): void
    {
        $pdfBytes = "%PDF-1.7\n%mock\n%%EOF\n";
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => [
                'code' => 200,
                'body' => [
                    'pdf_bytes_base64' => base64_encode($pdfBytes),
                    'ms' => 1,
                    'from_cache' => false,
                ],
            ],
        ]);
        $oracle = $this->makeOracle();
        $path = $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
        self::assertNotNull($path);
        self::assertFileExists($path);
        self::assertSame($pdfBytes, file_get_contents($path));
    }

    public function testRenderUsesCacheOnSecondCall(): void
    {
        $pdfBytes = "%PDF-1.7\n%mock\n%%EOF\n";
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => [
                'code' => 200,
                'body' => [
                    'pdf_bytes_base64' => base64_encode($pdfBytes),
                    'ms' => 1,
                    'from_cache' => false,
                ],
            ],
        ]);
        $oracle = $this->makeOracle();
        $first = $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
        self::assertNotNull($first);

        // Flip the mock to a 500 — if the cache doesn't hit, the second
        // render will throw. Cache key only depends on file bytes, so
        // the same fixture path produces the same key.
        $this->writeState([
            'status' => ['code' => 200, 'body' => ['engine' => 'chromium', 'ready' => true]],
            'render' => ['code' => 500, 'body' => ['error' => 'mock fail']],
        ]);
        $second = $oracle->render('chromium', self::$hostWptRoot . '/css/flex.html');
        self::assertSame($first, $second);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function makeOracle(): BrowserOracle
    {
        return new BrowserOracle(
            cacheDir: self::$cacheDir,
            daemonBase: self::$daemonBase,
            hostWptRoot: self::$hostWptRoot,
        );
    }

    private function writeState(array $state): void
    {
        file_put_contents(self::$stateFile, json_encode($state));
    }

    /**
     * Probe a band of ports whose last digit matches `$lastDigit` until
     * one binds. Used because the oracle constructs URLs as
     * `${base}${offset}` and tests need to control the offset's
     * landing port.
     */
    private static function findFreePortEndingIn(int $lastDigit): int
    {
        // Start somewhere in the registered/dynamic ports range that
        // ends in the requested digit. Skip by 10 so the last digit
        // stays put. The 200 cap is plenty — every typical CI / dev
        // box has thousands of free ports in this band.
        $start = 28000 + $lastDigit;
        for ($p = $start; $p < $start + 2000; $p += 10) {
            $srv = @stream_socket_server('tcp://127.0.0.1:' . $p, $errno, $errstr);
            if (is_resource($srv)) {
                fclose($srv);
                return $p;
            }
        }
        self::fail("could not find a free port ending in $lastDigit in the 28000+ band");
    }

    private static function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            if ($f->isDir()) {
                @rmdir($f->getPathname());
            } else {
                @unlink($f->getPathname());
            }
        }
        @rmdir($dir);
    }
}
