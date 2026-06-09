<?php

declare(strict_types=1);

/**
 * Mock engine daemon for BrowserOracle daemon-mode tests. Reads its
 * desired behavior from a JSON state file whose path is supplied via
 * the `MOCK_DAEMON_STATE` env var. Each test writes the file, then
 * calls into BrowserOracle and asserts how the oracle reacts.
 *
 * State schema:
 *   {
 *     "status": { "code": 200, "body": { "engine": "chromium", "ready": true, ... } },
 *     "render": { "code": 200, "body": { "pdf_bytes_base64": "...", ... } }
 *   }
 *
 * Bodies are emitted verbatim as JSON (or as a raw string when
 * `raw: true` is set on the route). A `delay_ms` field on either
 * route forces the responder to sleep that many milliseconds, used
 * to exercise the oracle's timeout path.
 *
 * Anything not covered by state → 404.
 */

$statePath = getenv('MOCK_DAEMON_STATE');
if (!is_string($statePath) || $statePath === '' || !is_file($statePath)) {
    http_response_code(503);
    header('content-type: application/json');
    echo json_encode(['error' => 'mock daemon: missing state file']);
    return true;
}
$state = json_decode((string) file_get_contents($statePath), true);
if (!is_array($state)) {
    http_response_code(503);
    header('content-type: application/json');
    echo json_encode(['error' => 'mock daemon: malformed state file']);
    return true;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routeKey = match (true) {
    $method === 'GET' && $uri === '/status' => 'status',
    $method === 'POST' && $uri === '/render' => 'render',
    $method === 'POST' && $uri === '/flush' => 'flush',
    default => null,
};

if ($routeKey === null || !isset($state[$routeKey])) {
    http_response_code(404);
    header('content-type: application/json');
    echo json_encode(['error' => 'mock daemon: unmocked route']);
    return true;
}

$route = $state[$routeKey];
if (isset($route['delay_ms']) && is_int($route['delay_ms']) && $route['delay_ms'] > 0) {
    usleep($route['delay_ms'] * 1000);
}
http_response_code((int) ($route['code'] ?? 200));
if (!empty($route['raw'])) {
    header('content-type: ' . ($route['content_type'] ?? 'text/plain'));
    echo (string) ($route['body'] ?? '');
} else {
    header('content-type: application/json');
    echo json_encode($route['body'] ?? new stdClass());
}
return true;
