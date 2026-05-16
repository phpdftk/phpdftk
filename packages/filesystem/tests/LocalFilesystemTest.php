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

    // ------------------------------------------------------------------
    // Stream-wrapper rejection — exercise every entry point
    // ------------------------------------------------------------------

    public function testReadPrefixRejectsHttpScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream wrappers are not allowed');
        LocalFilesystem::readPrefix('http://example.com/file.pdf', 10);
    }

    public function testOpenReadableRejectsStreamWrapper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream wrappers are not allowed');
        LocalFilesystem::openReadable('php://temp');
    }

    public function testAssertReadableFileRejectsStreamWrapper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream wrappers are not allowed');
        LocalFilesystem::assertReadableFile('phar://archive.phar/x.txt');
    }

    public function testAssertLocalPathAcceptsRelativePath(): void
    {
        // Plain relative paths must not trigger the rejection branch.
        LocalFilesystem::assertLocalPath('./relative/path.txt');
        LocalFilesystem::assertLocalPath('plain-name.pdf');
        LocalFilesystem::assertLocalPath('/absolute/path.pdf');
        // Colon-but-not-stream-wrapper (e.g., Windows drive letter is rare on unix CI
        // but the pattern would also reject "C://" — test plain colons in filename:
        LocalFilesystem::assertLocalPath('weird:name.pdf');
        $this->addToAssertionCount(1);
    }

    public function testAssertLocalPathRejectsDataScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LocalFilesystem::assertLocalPath('data://text/plain;base64,SGVsbG8=');
    }

    public function testAssertLocalPathRejectsCompressZlibScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LocalFilesystem::assertLocalPath('compress.zlib://file.gz');
    }

    // ------------------------------------------------------------------
    // Path exists but is not readable / not a regular file
    // ------------------------------------------------------------------

    public function testReadFileRejectsDirectoryPath(): void
    {
        // A directory path passes assertLocalPath but fails is_file in
        // assertReadableFile, exercising the "is_file is false" branch.
        $dir = sys_get_temp_dir() . '/phpdftk_fs_dirtest_' . uniqid();
        mkdir($dir);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Cannot read');
            LocalFilesystem::readFile($dir);
        } finally {
            @rmdir($dir);
        }
    }

    public function testReadFileRejectsUnreadableFile(): void
    {
        if (\function_exists('posix_geteuid') && \posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root — cannot test unreadable files');
        }
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_fs_chmod_');
        file_put_contents($path, 'secret');
        chmod($path, 0000);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Cannot read');
            LocalFilesystem::readFile($path);
        } finally {
            chmod($path, 0644);
            @unlink($path);
        }
    }

    public function testReadPrefixRejectsUnreadableFile(): void
    {
        if (\function_exists('posix_geteuid') && \posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root — cannot test unreadable files');
        }
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_fs_chmod_');
        file_put_contents($path, 'secret');
        chmod($path, 0000);
        try {
            $this->expectException(\RuntimeException::class);
            LocalFilesystem::readPrefix($path, 4);
        } finally {
            chmod($path, 0644);
            @unlink($path);
        }
    }

    public function testOpenReadableRejectsUnreadableFile(): void
    {
        if (\function_exists('posix_geteuid') && \posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root — cannot test unreadable files');
        }
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_fs_chmod_');
        file_put_contents($path, 'secret');
        chmod($path, 0000);
        try {
            $this->expectException(\RuntimeException::class);
            LocalFilesystem::openReadable($path);
        } finally {
            chmod($path, 0644);
            @unlink($path);
        }
    }

    public function testAssertReadableFileWithDirectoryThrows(): void
    {
        $dir = sys_get_temp_dir() . '/phpdftk_fs_dirtest_' . uniqid();
        mkdir($dir);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Cannot read');
            LocalFilesystem::assertReadableFile($dir, 'asset');
        } finally {
            @rmdir($dir);
        }
    }

    public function testLabelAppearsInErrorMessage(): void
    {
        $missing = '/nonexistent/' . uniqid();
        try {
            LocalFilesystem::readFile($missing, 'custom-label');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('custom-label', $e->getMessage());
            $this->assertStringContainsString($missing, $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // writeFile failure paths
    // ------------------------------------------------------------------

    public function testWriteFileFailsWhenMkdirCannotCreate(): void
    {
        // Make a regular file, then ask writeFile to put data in a "subdirectory"
        // of that file. mkdir() can't create a directory under a regular file,
        // so the createDirectories branch fails and the second is_dir check
        // also fails → throw.
        $blocker = tempnam(sys_get_temp_dir(), 'phpdftk_fs_block_');
        $impossiblePath = $blocker . '/inner/file.txt';
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Directory does not exist');
            @LocalFilesystem::writeFile($impossiblePath, 'data', createDirectories: true);
        } finally {
            @unlink($blocker);
        }
    }

    public function testWriteFileFailsWhenPathIsExistingDirectory(): void
    {
        // file_put_contents() returns false when given a directory path
        // (it can't open a directory for writing). This exercises the
        // "Cannot write file" branch.
        $dir = sys_get_temp_dir() . '/phpdftk_fs_writedir_' . uniqid();
        mkdir($dir);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Cannot write file');
            @LocalFilesystem::writeFile($dir, 'data');
        } finally {
            @rmdir($dir);
        }
    }

    public function testWriteFileSucceedsWhenDirAlreadyExists(): void
    {
        // Sanity check: when parent dir already exists, neither mkdir-failure
        // nor missing-dir branches fire, and file_put_contents succeeds.
        $dir = sys_get_temp_dir() . '/phpdftk_fs_existing_' . uniqid();
        mkdir($dir);
        $path = $dir . '/out.txt';
        try {
            LocalFilesystem::writeFile($path, 'payload', createDirectories: true);
            $this->assertSame('payload', file_get_contents($path));
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }

    public function testWriteFileOverwritesExistingFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_fs_over_');
        try {
            file_put_contents($path, 'original');
            LocalFilesystem::writeFile($path, 'replacement');
            $this->assertSame('replacement', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
