<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Tests\Support;

use Phpdftk\ResourceLoader\Exception\FetchFailedException;
use Phpdftk\ResourceLoader\Transport\RawResponse;
use Phpdftk\ResourceLoader\Transport\TransportInterface;

/**
 * In-test HTTP transport. Records each `send()` call and returns
 * the next queued response. Throws if the queue is empty so a test
 * that under-queues doesn't silently fall through to "send returned
 * null".
 *
 * Lives under `tests/Support/` so it's not autoloaded into the
 * production runtime; the `autoload-dev` namespace
 * `Phpdftk\ResourceLoader\Tests\` picks it up.
 */
final class FakeTransport implements TransportInterface
{
    /** @var list<RawResponse> */
    private array $queue = [];

    /** @var list<array{url: string, headers: array<string, string>, timeout: int, maxBody: int}> */
    public array $calls = [];

    public function queue(RawResponse $response): self
    {
        $this->queue[] = $response;
        return $this;
    }

    public function queueRedirect(string $location, int $status = 302): self
    {
        return $this->queue(new RawResponse(
            statusCode: $status,
            headers: ['location' => $location],
            body: '',
            finalUrl: '<set-by-fetcher>',
        ));
    }

    public function queueOk(string $body, string $contentType = 'application/octet-stream'): self
    {
        return $this->queue(new RawResponse(
            statusCode: 200,
            headers: ['content-type' => $contentType],
            body: $body,
            finalUrl: '<set-by-fetcher>',
        ));
    }

    public function send(string $url, array $headers, int $timeoutSeconds, int $maxBodyBytes): RawResponse
    {
        $this->calls[] = [
            'url' => $url,
            'headers' => $headers,
            'timeout' => $timeoutSeconds,
            'maxBody' => $maxBodyBytes,
        ];
        if ($this->queue === []) {
            throw new FetchFailedException(sprintf('FakeTransport had no response queued for %s', $url));
        }
        $next = array_shift($this->queue);
        return new RawResponse(
            statusCode: $next->statusCode,
            headers: $next->headers,
            body: $next->body,
            finalUrl: $url,
        );
    }
}
