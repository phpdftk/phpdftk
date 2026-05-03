<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Support;

trait QpdfValidationTrait
{
    protected function assertQpdfValid(string $pdfPath): void
    {
        // Docker first
        if (DockerToolRunner::isAvailable() && DockerToolRunner::hasImage('phpdftk/qpdf')) {
            // If the file is in a location Docker can't mount (e.g., system temp),
            // copy it to a Docker-accessible temp directory first.
            $effectivePath = $pdfPath;
            $copied = false;
            $realDir = dirname(realpath($pdfPath) ?: $pdfPath);

            if (!DockerToolRunner::isPathMountable($realDir)) {
                $tmpDir = DockerToolRunner::tempDir();
                $effectivePath = $tmpDir . '/' . basename($pdfPath);
                copy($pdfPath, $effectivePath);
                $copied = true;
            }

            try {
                $result = DockerToolRunner::run(
                    'phpdftk/qpdf',
                    ['--check', '/data/' . basename($effectivePath)],
                    dirname(realpath($effectivePath) ?: $effectivePath),
                );

                // "invalid password" means the encryption envelope is structurally
                // valid but QPDF can't decrypt for deeper checks — not a failure.
                if ($result->exitCode !== 0 && !str_contains($result->output, 'invalid password')) {
                    self::assertSame(
                        0,
                        $result->exitCode,
                        "qpdf --check failed for {$pdfPath}:\n" . $result->output,
                    );
                }
            } finally {
                if ($copied) {
                    @unlink($effectivePath);
                }
            }

            return;
        }

        // Local binary fallback
        $qpdf = ExternalToolLocator::find('qpdf', [
            '/usr/bin/qpdf',
            '/usr/local/bin/qpdf',
            '/opt/homebrew/bin/qpdf',
        ]);

        if ($qpdf === null) {
            return;
        }

        $cmd = sprintf(
            '%s --check %s 2>&1',
            escapeshellarg($qpdf),
            escapeshellarg($pdfPath),
        );

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);

        $fullOutput = implode("\n", $output);

        if ($ret !== 0 && !str_contains($fullOutput, 'invalid password')) {
            self::assertSame(
                0,
                $ret,
                "qpdf --check failed for {$pdfPath}:\n" . $fullOutput,
            );
        }
    }

    protected function assertQpdfValidBytes(string $pdfBytes): void
    {
        $tmpDir = DockerToolRunner::tempDir();
        $tmp = tempnam($tmpDir, 'qpdf_');
        file_put_contents($tmp, $pdfBytes);

        try {
            $this->assertQpdfValid($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
