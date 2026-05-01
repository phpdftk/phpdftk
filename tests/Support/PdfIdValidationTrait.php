<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support;

/**
 * pdfid.py security scanner validation trait for PDF files.
 *
 * Validates PDFs using Didier Stevens' pdfid.py, checking for suspicious
 * indicators (/JS, /JavaScript, /AA, /OpenAction, /Launch).
 * Docker image: phpdftk/pdfid. Local fallback: pdfid.py CLI.
 */
trait PdfIdValidationTrait
{
    private static ?string $pdfIdMethod = null;
    private static bool $pdfIdChecked = false;

    /**
     * Assert that a PDF file has no suspicious security indicators.
     */
    protected function assertPdfIdClean(string $pdfPath): void
    {
        $method = $this->findPdfId();
        if ($method === null) {
            $this->markTestSkipped('pdfid.py not available (install CLI or Docker image phpdftk/pdfid)');
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
                'phpdftk/pdfid',
                ['/data/' . $filename],
                $volumePath,
            );

            $indicators = $this->parsePdfIdIndicators($result->output);
            $this->assertIndicatorsClean($indicators, $pdfPath, $result->output);

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
        exec($cmd, $output);
        $fullOutput = implode("\n", $output);

        $indicators = $this->parsePdfIdIndicators($fullOutput);
        $this->assertIndicatorsClean($indicators, $pdfPath, $fullOutput);
    }

    /**
     * Run pdfid.py and return raw output for custom assertions.
     */
    protected function runPdfIdRaw(string $pdfPath): DockerToolResult|string
    {
        $method = $this->findPdfId();
        if ($method === null) {
            $this->markTestSkipped('pdfid.py not available');
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
                'phpdftk/pdfid',
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

    /**
     * Parse pdfid.py output into an associative array of indicator counts.
     *
     * pdfid.py outputs lines like:
     *   /JS                    0
     *   /JavaScript            0
     *
     * @return array<string, int>
     */
    protected function parsePdfIdIndicators(string $output): array
    {
        $suspicious = ['JS', 'JavaScript', 'AA', 'OpenAction', 'Launch'];
        $indicators = [];

        foreach ($suspicious as $name) {
            $indicators[$name] = 0;
        }

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^\s*\/(\w+)\s+(\d+)/', $line, $matches)) {
                $key = $matches[1];
                if (in_array($key, $suspicious, true)) {
                    $indicators[$key] = (int) $matches[2];
                }
            }
        }

        return $indicators;
    }

    protected function findPdfId(): ?string
    {
        if (self::$pdfIdChecked) {
            return self::$pdfIdMethod;
        }
        self::$pdfIdChecked = true;

        // Docker first
        if (DockerToolRunner::isAvailable() && DockerToolRunner::hasImage('phpdftk/pdfid')) {
            return self::$pdfIdMethod = 'docker';
        }

        // Local binary fallback
        $binary = ExternalToolLocator::find('pdfid.py', [
            '/usr/local/bin/pdfid.py',
            '/opt/homebrew/bin/pdfid.py',
        ]);
        if ($binary !== null) {
            return self::$pdfIdMethod = $binary;
        }

        return self::$pdfIdMethod = null;
    }

    /**
     * Assert all suspicious indicators are zero.
     *
     * @param array<string, int> $indicators
     */
    private function assertIndicatorsClean(array $indicators, string $pdfPath, string $rawOutput): void
    {
        $failures = [];
        foreach ($indicators as $name => $count) {
            if ($count > 0) {
                $failures[] = "/{$name} = {$count}";
            }
        }

        if ($failures !== []) {
            self::fail(sprintf(
                "pdfid.py found suspicious indicators in %s:\n  %s\n\nFull output:\n%s",
                $pdfPath,
                implode("\n  ", $failures),
                substr($rawOutput, 0, 2000),
            ));
        }
    }
}
