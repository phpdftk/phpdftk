<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance;

use Phpdftk\Pdf\Conformance\Result\ConformanceResult;

/**
 * Thrown in strict mode when the document does not conform.
 */
final class ConformanceException extends \RuntimeException
{
    /** @var list<ConformanceResult> */
    public readonly array $results;

    /** @param list<ConformanceResult> $results */
    public function __construct(array $results)
    {
        $this->results = $results;

        $messages = [];
        foreach ($results as $result) {
            if (!$result->isCompliant) {
                $errors = $result->getErrors();
                $messages[] = sprintf(
                    '%s-%s: %d violation(s) — %s',
                    $result->profile->getFamily(),
                    $result->profile->getLevel(),
                    count($errors),
                    $errors[0]->message ?? 'unknown',
                );
            }
        }

        parent::__construct('Conformance validation failed: ' . implode('; ', $messages));
    }
}
