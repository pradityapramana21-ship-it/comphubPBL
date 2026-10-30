<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $headers
     * @param array<string, mixed> $files
     * @param array<string, string> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private array $input = [],
        private array $headers = [],
        private array $files = [],
        private readonly array $server = [],
        private readonly string $queryString = '',
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $input = $_POST;

        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        if (isset($_GET) && is_array($_GET)) {
            $input += $_GET;
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        $method = self::resolveMethod($method, $input);
        $queryString = parse_url($uri, PHP_URL_QUERY);
        $queryString = is_string($queryString) ? $queryString : '';

        return new self($method, self::normalisePath($path), $input, $headers, $_FILES, $_SERVER, $queryString);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $headers
     * @param array<string, mixed> $files
     */
    public static function create(
        string $method,
        string $path,
        array $input = [],
        array $headers = [],
        array $files = [],
    ): self {
        $query = [];
        $parsedPath = parse_url($path, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($path, PHP_URL_QUERY);

        if (is_string($queryString)) {
            parse_str($queryString, $query);
        }

        $input += $query;

        return new self(
            self::resolveMethod(strtoupper($method), $input),
            self::normalisePath($parsedPath),
            $input,
            $headers,
            $files,
            ['REMOTE_ADDR' => '127.0.0.1'],
            is_string($queryString) ? $queryString : '',
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function fullPath(): string
    {
        return $this->path . ($this->queryString !== '' ? '?' . $this->queryString : '');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_string($value) ? trim($value) : $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->input;
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->input)) {
                $result[$key] = $this->input[$key];
            }
        }

        return $result;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->input);
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function wantsJson(): bool
    {
        return str_contains(strtolower((string) $this->header('accept', '')), 'application/json');
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /** @param array<string, mixed> $input */
    private static function resolveMethod(string $method, array $input): string
    {
        $override = is_string($input['_method'] ?? null) ? strtoupper($input['_method']) : '';

        return $method === 'POST' && in_array($override, ['PUT', 'PATCH', 'DELETE'], true)
            ? $override
            : $method;
    }

    private static function normalisePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
