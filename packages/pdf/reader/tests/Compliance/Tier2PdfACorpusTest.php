<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Compliance;

use Phpdftk\Tests\Support\DockerToolRunner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tier 2 — veraPDF compliance tests against the veraPDF test corpus.
 *
 * The corpus contains both "pass" and "fail" PDFs. We assert that:
 * - Files with "fail" in the name are reported as non-compliant
 * - Files with "pass" in the name are reported as compliant
 *
 * Uses a single Docker invocation with recursive scanning for performance
 * (processes all PDFs in ~60s vs hours with one container per file).
 *
 * Run with: vendor/bin/phpunit --group tier2-pdfa
 */
#[Group('tier2')]
#[Group('tier2-pdfa')]
#[Group('verapdf')]
class Tier2PdfACorpusTest extends TestCase
{
    private const CORPUS_DIR = __DIR__ . '/../../../../../vendor-data/verapdf-corpus';

    public function testPdfA1bCorpus(): void
    {
        $corpusPath = realpath(self::CORPUS_DIR . '/PDF_A-1b');
        if ($corpusPath === false || !is_dir($corpusPath)) {
            $this->markTestSkipped('PDF/A-1b corpus not available (vendor-data/verapdf-corpus submodule not initialized)');
        }

        $output = $this->runVeraPdfRecursive($corpusPath, '1b');
        $this->assertCorpusResults($output, 'PDF/A-1b');
    }

    public function testPdfA2bCorpus(): void
    {
        $corpusPath = realpath(self::CORPUS_DIR . '/PDF_A-2b');
        if ($corpusPath === false || !is_dir($corpusPath)) {
            $this->markTestSkipped('PDF/A-2b corpus not available (vendor-data/verapdf-corpus submodule not initialized)');
        }

        $output = $this->runVeraPdfRecursive($corpusPath, '2b');
        $this->assertCorpusResults($output, 'PDF/A-2b');
    }

    private function runVeraPdfRecursive(string $directory, string $profile): string
    {
        if (!DockerToolRunner::isAvailable() || !DockerToolRunner::hasImage('verapdf/cli')) {
            $this->markTestSkipped('veraPDF Docker image not available');
        }

        $result = DockerToolRunner::run(
            'verapdf/cli',
            ['--format', 'mrr', '-f', $profile, '-r', '/data'],
            $directory,
        );

        return $result->output;
    }

    /**
     * Parse veraPDF MRR output and assert that "fail" files are non-compliant
     * and "pass" files are compliant.
     */
    private function assertCorpusResults(string $output, string $corpusName): void
    {
        // Parse all job results: filename + compliance status
        $mismatches = [];
        $totalJobs = 0;

        if (preg_match_all(
            '/<job>.*?<name>([^<]+)<\/name>.*?<validationReport[^>]*isCompliant="([^"]+)"[^>]*>/s',
            $output,
            $matches,
            PREG_SET_ORDER,
        )) {
            $totalJobs = count($matches);
            foreach ($matches as $match) {
                $filename = basename($match[1]);
                $isCompliant = $match[2] === 'true';

                $expectFail = str_contains($filename, 'fail');
                $expectPass = str_contains($filename, 'pass');

                if ($expectFail && $isCompliant) {
                    $mismatches[] = "$filename: expected non-compliant, got compliant";
                } elseif ($expectPass && !$isCompliant) {
                    $mismatches[] = "$filename: expected compliant, got non-compliant";
                }
            }
        }

        self::assertGreaterThan(0, $totalJobs, "$corpusName corpus: veraPDF processed no files");

        if ($mismatches !== []) {
            self::fail(sprintf(
                "%s corpus: %d/%d files had unexpected results:\n  - %s",
                $corpusName,
                count($mismatches),
                $totalJobs,
                implode("\n  - ", array_slice($mismatches, 0, 30)),
            ));
        }
    }
}
