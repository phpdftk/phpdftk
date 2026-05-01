<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support;

/**
 * Apache PDFBox Preflight PDF/A-1b validation trait for PDF files.
 *
 * Validates PDFs using Apache PDFBox Preflight, checking for PDF/A-1b compliance.
 * Docker image: phpdftk/pdfbox-preflight. Local fallback: preflight CLI.
 */
trait PdfBoxPreflightValidationTrait
{
    private static ?string $pdfBoxPreflightMethod = null;
    private static bool $pdfBoxPreflightChecked = false;

    /**
     * Assert that a PDF file is valid PDF/A-1b according to PDFBox Preflight.
     */
    protected function assertPdfBoxPreflightValid(string $pdfPath): void
    {
        $method = $this->findPdfBoxPreflight();
        if ($method === null) {
            $this->markTestSkipped('PDFBox Preflight not available (install CLI or Docker image phpdftk/pdfbox-preflight)');
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
                'phpdftk/pdfbox-preflight',
                ['/data/' . $filename],
                $volumePath,
            );

            if ($result->exitCode !== 0) {
                self::fail(sprintf(
                    "PDFBox Preflight PDF/A-1b validation failed for %s (exit code %d):\n%s",
                    $pdfPath,
                    $result->exitCode,
                    substr($result->output, 0, 2000),
                ));
            }

            // Clean up temp file if we copied it
            if (isset($tmpFile) && file_exists($tmpFile)) {
                unlink($tmpFile);
            }

            return;
        }

        // Local binary
        $cmd = sprintf(
            '%s %s 2>&1',
            escapeshellarg($method),
            escapeshellarg($pdfPath),
        );

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);
        $fullOutput = implode("\n", $output);

        if ($ret !== 0) {
            self::fail(sprintf(
                "PDFBox Preflight PDF/A-1b validation failed for %s (exit code %d):\n%s",
                $pdfPath,
                $ret,
                substr($fullOutput, 0, 2000),
            ));
        }
    }

    /**
     * Run PDFBox Preflight and return raw output for custom assertions.
     */
    protected function runPdfBoxPreflightRaw(string $pdfPath): DockerToolResult|string
    {
        $method = $this->findPdfBoxPreflight();
        if ($method === null) {
            $this->markTestSkipped('PDFBox Preflight not available');
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
                'phpdftk/pdfbox-preflight',
                ['/data/' . $filename],
                $volumePath,
            );
        }

        $cmd = sprintf(
            '%s %s 2>&1',
            escapeshellarg($method),
            escapeshellarg($pdfPath),
        );

        $output = [];
        exec($cmd, $output);

        return implode("\n", $output);
    }

    protected function findPdfBoxPreflight(): ?string
    {
        if (self::$pdfBoxPreflightChecked) {
            return self::$pdfBoxPreflightMethod;
        }
        self::$pdfBoxPreflightChecked = true;

        // Docker first
        if (DockerToolRunner::isAvailable() && DockerToolRunner::hasImage('phpdftk/pdfbox-preflight')) {
            return self::$pdfBoxPreflightMethod = 'docker';
        }

        // Local binary fallback
        $binary = ExternalToolLocator::find('preflight', [
            '/usr/local/bin/preflight',
            '/opt/homebrew/bin/preflight',
        ]);
        if ($binary !== null) {
            return self::$pdfBoxPreflightMethod = $binary;
        }

        return self::$pdfBoxPreflightMethod = null;
    }
}
