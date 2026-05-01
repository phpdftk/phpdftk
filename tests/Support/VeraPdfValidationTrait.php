<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support;

trait VeraPdfValidationTrait
{
    private static ?string $veraPdfMethod = null;
    private static bool $veraPdfChecked = false;

    /**
     * Assert that a PDF file is compliant with the given PDF/A profile.
     *
     * Profiles: '1a', '1b', '2a', '2b', '2u', '3a', '3b', '3u', '4', 'ua1'.
     */
    protected function assertVeraPdfCompliant(string $pdfPath, string $profile = '1b'): void
    {
        $method = $this->findVeraPdf();
        if ($method === null) {
            $this->markTestSkipped('veraPDF not available (install CLI, Docker image verapdf/cli, or run: cd docker && docker compose pull)');
        }

        if ($method === 'docker') {
            $result = DockerToolRunner::run(
                'verapdf/cli',
                ['--format', 'mrr', '-f', $profile, '/data/' . basename($pdfPath)],
                dirname($pdfPath),
            );

            $fullOutput = $result->output;

            if ($result->exitCode !== 0) {
                $violations = $this->parseVeraPdfViolations($fullOutput);
                self::fail("veraPDF PDF/A-{$profile} validation failed for {$pdfPath}:\n{$violations}");
            }

            if (str_contains($fullOutput, 'isCompliant="false"')) {
                $violations = $this->parseVeraPdfViolations($fullOutput);
                self::fail("veraPDF reports non-compliance for {$pdfPath} (PDF/A-{$profile}):\n{$violations}");
            }

            return;
        }

        // Local binary path
        $cmd = sprintf(
            '%s --format mrr -f %s %s 2>&1',
            escapeshellarg($method),
            escapeshellarg($profile),
            escapeshellarg($pdfPath),
        );

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);

        $fullOutput = implode("\n", $output);

        if ($ret !== 0) {
            $violations = $this->parseVeraPdfViolations($fullOutput);
            self::fail("veraPDF PDF/A-{$profile} validation failed for {$pdfPath}:\n{$violations}");
        }

        if (str_contains($fullOutput, 'isCompliant="false"')) {
            $violations = $this->parseVeraPdfViolations($fullOutput);
            self::fail("veraPDF reports non-compliance for {$pdfPath} (PDF/A-{$profile}):\n{$violations}");
        }
    }

    protected function runVeraPdfRaw(string $pdfPath, string $profile = '1b'): DockerToolResult|string
    {
        $method = $this->findVeraPdf();
        if ($method === null) {
            $this->markTestSkipped('veraPDF not available');
        }

        if ($method === 'docker') {
            return DockerToolRunner::run(
                'verapdf/cli',
                ['--format', 'mrr', '-f', $profile, '/data/' . basename($pdfPath)],
                dirname($pdfPath),
            );
        }

        $cmd = sprintf(
            '%s --format mrr -f %s %s 2>&1',
            escapeshellarg($method),
            escapeshellarg($profile),
            escapeshellarg($pdfPath),
        );

        $output = [];
        exec($cmd, $output);

        return implode("\n", $output);
    }

    protected function parseVeraPdfViolations(string $xmlOutput): string
    {
        if (preg_match_all('/<ruleId[^>]*clause="([^"]*)"[^>]*testNumber="(\d+)"[^>]*\/>/', $xmlOutput, $matches, PREG_SET_ORDER)) {
            $violations = [];
            foreach (array_slice($matches, 0, 20) as $match) {
                $violations[] = "  - Clause {$match[1]}, test {$match[2]}";
            }
            $count = count($matches);
            $result = implode("\n", $violations);
            if ($count > 20) {
                $result .= "\n  ... and " . ($count - 20) . ' more violations';
            }
            return $result;
        }

        return substr($xmlOutput, 0, 2000);
    }

    protected function findVeraPdf(): ?string
    {
        if (self::$veraPdfChecked) {
            return self::$veraPdfMethod;
        }
        self::$veraPdfChecked = true;

        // Docker first
        if (DockerToolRunner::isAvailable() && DockerToolRunner::hasImage('verapdf/cli')) {
            return self::$veraPdfMethod = 'docker';
        }

        // Local binary fallback
        $binary = ExternalToolLocator::find('verapdf', [
            '/usr/local/bin/verapdf',
            '/opt/homebrew/bin/verapdf',
        ]);
        if ($binary !== null) {
            return self::$veraPdfMethod = $binary;
        }

        return self::$veraPdfMethod = null;
    }
}
