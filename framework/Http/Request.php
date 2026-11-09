<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Immutable-ish wrapper around the PHP superglobals. Post/put/delete bodies
 * arrive via $_POST or a JSON payload; `_method` enables HTML form spoofing.
 */
final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $body = [],
        private array $server = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $body = $_POST;

        if (isset($body['_method']) && is_string($body['_method'])) {
            $method = strtoupper($body['_method']);
        }

        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);

            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self($method, $path, $_GET, $body, $_SERVER);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Body value first, query string second.
     */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : $default;
    }
}
