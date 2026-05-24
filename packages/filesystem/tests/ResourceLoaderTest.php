<?php

declare(strict_types=1);

namespace Phpdftk\Filesystem\Tests;

use Phpdftk\Filesystem\ResourceLoader;
use PHPUnit\Framework\TestCase;

final class ResourceLoaderTest extends TestCase
{
    public function testEmptyUrlReturnsNull(): void
    {
        $loader = new ResourceLoader();
        self::assertNull($loader->load(''));
        self::assertNull($loader->resolveLocalPath(''));
    }

    public function testDataUrlBase64Decoded(): void
    {
        $loader = new ResourceLoader();
        $bytes = $loader->load('data:text/css;base64,' . base64_encode('p { color: red }'));
        self::assertSame('p { color: red }', $bytes);
    }

    public function testDataUrlUrlEncoded(): void
    {
        $loader = new ResourceLoader();
        $bytes = $loader->load('data:text/css,p%20%7B%20color%3A%20red%20%7D');
        self::assertSame('p { color: red }', $bytes);
    }

    public function testDataUrlMimeAllowlistMatchPasses(): void
    {
        $loader = new ResourceLoader();
        $bytes = $loader->load(
            'data:text/css;base64,' . base64_encode('body{}'),
            allowedMimes: ['text/css'],
        );
        self::assertSame('body{}', $bytes);
    }

    public function testDataUrlMimeAllowlistMismatchRejected(): void
    {
        $loader = new ResourceLoader();
        $bytes = $loader->load(
            'data:application/octet-stream;base64,QUJD',
            allowedMimes: ['text/css'],
        );
        self::assertNull($bytes, 'MIME mismatch rejected');
    }

    public function testHttpsSchemeRejected(): void
    {
        $loader = new ResourceLoader(baseDir: sys_get_temp_dir());
        self::assertNull($loader->load('https://example.com/font.otf'));
        self::assertNull($loader->resolveLocalPath('https://example.com/font.otf'));
    }

    public function testNoBaseDirRejectsRelativePath(): void
    {
        $loader = new ResourceLoader();
        self::assertNull($loader->load('foo.css'));
        self::assertNull($loader->resolveLocalPath('foo.css'));
    }

    public function testLocalFileLoadedFromBaseDir(): void
    {
        $baseDir = sys_get_temp_dir() . '/phpdftk-rl-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        try {
            file_put_contents($baseDir . '/sample.css', 'p { margin: 0; }');
            $loader = new ResourceLoader($baseDir);
            self::assertSame('p { margin: 0; }', $loader->load('sample.css'));
            self::assertSame(
                realpath($baseDir . '/sample.css'),
                $loader->resolveLocalPath('sample.css'),
            );
        } finally {
            @unlink($baseDir . '/sample.css');
            @rmdir($baseDir);
        }
    }

    public function testEscapeViaParentRejected(): void
    {
        $baseDir = sys_get_temp_dir() . '/phpdftk-rl-esc-' . bin2hex(random_bytes(4));
        mkdir($baseDir);
        try {
            $loader = new ResourceLoader($baseDir);
            self::assertNull(
                $loader->load('../etc/passwd'),
                'parent-walk attempt rejected',
            );
        } finally {
            @rmdir($baseDir);
        }
    }

    public function testStreamWrapperRejectedAtLoadTime(): void
    {
        $loader = new ResourceLoader(baseDir: sys_get_temp_dir());
        self::assertNull($loader->load('php://filter/resource=/etc/hosts'));
    }
}
