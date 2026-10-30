<?php

declare(strict_types=1);

namespace App\Core;

final class Route
{
    /** @var list<string> */
    private array $middleware = [];

    private ?string $routeName = null;

    /** @param list<string> $methods */
    public function __construct(
        private readonly array $methods,
        private readonly string $path,
        private readonly mixed $handler,
    ) {
    }

    public function middleware(string ...$middleware): self
    {
        foreach ($middleware as $name) {
            if ($name !== '') {
                $this->middleware[] = $name;
            }
        }

        return $this;
    }

    public function name(string $name): self
    {
        $this->routeName = $name;

        return $this;
    }

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    public function method(): string
    {
        return $this->methods[0];
    }

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    /** @return list<string> */
    public function middlewareList(): array
    {
        return $this->middleware;
    }

    /** @return array<string, string>|null */
    public function match(string $path): ?array
    {
        $pattern = '#^' . $this->pattern() . '$#u';
        $result = preg_match($pattern, $path, $matches);

        if ($result !== 1) {
            return null;
        }

        $params = [];

        foreach ($this->parameterNames() as $index => $name) {
            $params[$name] = $matches[$index + 1];
        }

        return $params;
    }

    /** @param array<string, string|int> $params */
    public function buildPath(array $params = []): string
    {
        $path = $this->path;

        foreach ($this->parameterNames() as $name) {
            if (! array_key_exists($name, $params)) {
                throw new \RuntimeException('Missing route parameter: ' . $name);
            }

            $path = preg_replace('#\{' . preg_quote($name, '#') . '(?::[a-z_]+)?\}#', rawurlencode((string) $params[$name]), $path) ?? $path;
            unset($params[$name]);
        }

        if ($params !== []) {
            $path .= '?' . http_build_query($params);
        }

        return $path;
    }

    private function pattern(): string
    {
        $parts = preg_split(
            '#(\{[a-zA-Z_][a-zA-Z0-9_]*(?::[a-z_]+)?\})#',
            $this->path,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        ) ?: [];

        $pattern = '';

        foreach ($parts as $part) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([a-z_]+))?\}$#', $part, $match) === 1) {
                $pattern .= ($match[2] ?? '') === 'id' ? '([0-9]+)' : '([^/]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return $pattern;
    }

    /** @return list<string> */
    private function parameterNames(): array
    {
        preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[a-z_]+)?\}#', $this->path, $matches);

        return $matches[1] ?? [];
    }
}
