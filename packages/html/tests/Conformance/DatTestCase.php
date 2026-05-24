<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Conformance;

/**
 * A single test case extracted from an html5lib-tests `.dat` file.
 *
 * The five fields cover every section recognised by the format:
 *  - `data` — the HTML input
 *  - `expectedDocument` — the canonical-tree representation we compare against
 *  - `expectedErrors` — list of expected parse errors (Phase 1B.6 ignores)
 *  - `fragmentContext` — context element local name for fragment parsing
 *  - `scriptingEnabled` — null = either ok, true/false = constrained variant
 */
final class DatTestCase
{
    public string $data = '';
    public string $expectedDocument = '';
    /** @var list<string> */
    public array $expectedErrors = [];
    public ?string $fragmentContext = null;
    public ?bool $scriptingEnabled = null;
    /** 1-based index of this case in its source .dat file. */
    public int $index = 0;

    /**
     * Stable identifier for logging / ignored.txt entries: index plus a 60-char
     * preview. The index disambiguates cases that share a 60-char data prefix.
     */
    public function id(): string
    {
        $data = str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], $this->data);
        return '#' . $this->index . ':' . substr($data, 0, 60);
    }
}
