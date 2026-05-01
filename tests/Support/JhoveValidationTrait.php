<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support;

/**
 * JHOVE format validation trait for PDF files.
 *
 * Validates PDFs using JHOVE's PDF-hul module, checking for "Well-Formed and valid" status.
 * Docker image: openpreserve/jhove. Local fallback: jhove CLI.
 */
trait JhoveValidationTrait
{
    private static ?string $jhoveMethod = null;
    private static bool $jhoveChecked = false;

    /**
     * Assert that a PDF file is well-formed and valid according to JHOVE.
     */
    protected function assertJhoveValid(string $pdfPath): void
    {
        $method = $this->findJhove();
        if ($method === null) {
            $this->markTestSkipped('JHOVE not available (install CLI or Docker image openpreserve/jhove)');
        }

        if ($method === 'docker') {
            $volumePath = dirname($pdfPath);
            $filename = basename($pdfPath);

            // Handle non-mountable paths (macOS system temp)
            if (!DockerToolRunner::isPathMountable($volumePath)) {
                $tmpDir = DockerToolRunner::tempDir();
                $tmpFile = $tmpDir . '/' . $filename;
                copy($pdfPath, $tmpFile);
                $volumePath = $tmpDir;
            }

            $result = DockerToolRunner::run(
                'openpreserve/jhove',
                ['-m', 'PDF-hul', '-h', 'xml', '/data/' . $filename],
                $volumePath,
            );

            $status = $this->parseJhoveStatus($result->output);
            self::assertSame(
                'Well-Formed and valid',
                $status,
                "JHOVE validation failed for {$pdfPath}: status={$status}\n" . substr($result->output, 0, 2000),
            );

            // Clean up temp file if we copied it
            if (isset($tmpFile) && file_exists($tmpFile)) {
                unlink($tmpFile);
            }

            return;
        }

        // Local binary
        $cmd = sprintf(
            '%s -m PDF-hul -h xml %s 2>&1',
            escapeshellarg($method),
            escapeshellarg($pdfPath),
        );

        $output = [];
        exec($cmd, $output);
        $fullOutput = implode("\n", $output);

        $status = $this->parseJhoveStatus($fullOutput);
        self::assertSame(
            'Well-Formed and valid',
            $status,
            "JHOVE validation failed for {$pdfPath}: status={$status}\n" . substr($fullOutput, 0, 2000),
        );
    }

    /**
     * Run JHOVE and return raw output for custom assertions.
     */
    protected function runJhoveRaw(string $pdfPath): DockerToolResult|string
    {
        $method = $this->findJhove();
        if ($method === null) {
            $this->markTestSkipped('JHOVE not available');
        }

        if ($method === 'docker') {
            $volumePath = dirname($pdfPath);
            $filename = basename($pdfPath);

            if (!DockerToolRunner::isPathMountable($volumePath)) {
                $tmpDir = DockerToolRunner::tempDir();
                copy($pdfPath, $tmpDir . '/' . $filename);
                $volumePath = $tmpDir;
            }

            return DockerToolRunner::run(
                'openpreserve/jhove',
                ['-m', 'PDF-hul', '-h', 'xml', '/data/' . $filename],
                $volumePath,
            );
        }

        $cmd = sprintf(
            '%s -m PDF-hul -h xml %s 2>&1',
            escapeshellarg($method),
            escapeshellarg($pdfPath),
        );

        $output = [];
        exec($cmd, $output);

        return implode("\n", $output);
    }

    /**
     * Extract the <status> value from JHOVE XML output.
     */
    protected function parseJhoveStatus(string $xmlOutput): string
    {
        if (preg_match('/<status>([^<]+)<\/status>/', $xmlOutput, $matches)) {
            return trim($matches[1]);
        }

        return 'unknown (could not parse JHOVE output)';
    }

    protected function findJhove(): ?string
    {
        if (self::$jhoveChecked) {
            return self::$jhoveMethod;
        }
        self::$jhoveChecked = true;

        // Docker first
        if (DockerToolRunner::isAvailable() && DockerToolRunner::hasImage('openpreserve/jhove')) {
            return self::$jhoveMethod = 'docker';
        }

        // Local binary fallback
        $binary = ExternalToolLocator::find('jhove', [
            '/usr/local/bin/jhove',
            '/opt/homebrew/bin/jhove',
        ]);
        if ($binary !== null) {
            return self::$jhoveMethod = $binary;
        }

        return self::$jhoveMethod = null;
    }
}
