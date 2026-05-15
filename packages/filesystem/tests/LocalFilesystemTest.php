<?php

declare(strict_types=1);

namespace Phpdftk\Filesystem\Tests;

use Phpdftk\Filesystem\LocalFilesystem;
use PHPUnit\Framework\TestCase;

final class LocalFilesystemTest extends TestCase
{
    public function testReadFileRejectsStreamWrapperPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream wrappers are not allowed');

        LocalFilesystem::readFile('php://filter/read=convert.base64-encode/resource=/etc/passwd');
    }

    public function testReadPrefixReadsLocalFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_fs_');
        self::assertIsString($path);

        try {
            file_put_contents($path, 'abcdef');

            self::assertSame('abc', LocalFilesystem::readPrefix($path, 3));
        } finally {
            @unlink($path);
        }
    }

    public function testWriteFileRejectsStreamWrapperPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream wrappers are not allowed');

        LocalFilesystem::writeFile('php://memory', 'data');
    }

    public function testReadFileRoundTrip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_fs_');
        try {
            file_put_contents($path, 'hello world');
            $this->assertSame('hello world', LocalFilesystem::readFile($path));
        } finally {
            @unlink($path);
        }
    }

    public function testReadFileMissingThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        LocalFilesystem::readFile('/path/does/not/exist/' . uniqid());
    }

    public function testOpenReadableReturnsHandle(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_fs_');
        try {
            file_put_contents($path, 'open-me');
            $handle = LocalFilesystem::openReadable($path);
            $this->assertIsResource($handle);
            $this->assertSame('open-me', fread($handle, 7));
            fclose($handle);
        } finally {
            @unlink($path);
        }
    }

    public function testOpenReadableMissingThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        LocalFilesystem::openReadable('/path/does/not/exist/' . uniqid());
    }

    public function testWriteFileCreatesDirectoryWhenAllowed(): void
    {
        $dir = sys_get_temp_dir() . '/phpdftk_fs_nest_' . uniqid();
        $path = $dir . '/inner/file.txt';
        try {
            LocalFilesystem::writeFile($path, 'data', createDirectories: true);
            $this->assertFileExists($path);
            $this->assertSame('data', file_get_contents($path));
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
            @rmdir($dir . '/inner');
            @rmdir($dir);
        }
    }

    public function testWriteFileFailsWhenDirMissingAndCreateDisabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory does not exist');
        LocalFilesystem::writeFile(
            sys_get_temp_dir() . '/phpdftk_no_dir_' . uniqid() . '/inner/x.txt',
            'data',
            createDirectories: false,
        );
    }

    public function testReadPrefixRejectsStreamWrapper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LocalFilesystem::readPrefix('php://memory', 10);
    }

    public function testReadPrefixMissingThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        LocalFilesystem::readPrefix('/path/does/not/exist/' . uniqid(), 10);
    }
}
