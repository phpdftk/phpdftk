<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance;

use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;

/**
 * Value object holding the active conformance profile(s) and behavior mode.
 */
final readonly class ConformanceMode
{
    /** @var list<ConformanceProfile> */
    public array $profiles;

    /**
     * @param ConformanceProfile[] $profiles
     * @param bool $strict If true, throw on ERROR violations at generate() time
     */
    public function __construct(
        array $profiles,
        public bool $strict = true,
    ) {
        $this->profiles = array_values($profiles);
    }
}
