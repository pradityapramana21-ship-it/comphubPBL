<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Middleware\Middleware;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var callable(string): mixed|null */
    private $middlewareResolver = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function get(string $path, callable|array $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): Route
    {
        $route = new Route([strtoupper($method)], '/' . ltrim($path, '/'), $handler);
        $this->routes[] = $route;

        return $route;
    }

    /** @param callable(string): mixed $resolver */
    public function setMiddlewareResolver(callable $resolver): void
    {
        $this->middlewareResolver = $resolver;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $params = $route->match($request->path());

            if ($params === null) {
                continue;
            }

            $pathMatched = true;

            if (! in_array($request->method(), $route->methods(), true)) {
                $allowed = array_merge($allowed, $route->methods());
                continue;
            }

            $request->setAttribute('route', $params);

            return $this->runRoute($route, $request);
        }

        if ($pathMatched) {
            throw HttpException::methodNotAllowed();
        }

        throw HttpException::notFound();
    }

    /** @param array<string, string|int> $params */
    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->routeName() === $name) {
                return $route->buildPath($params);
            }
        }

        throw new \RuntimeException('Unknown route name: ' . $name);
    }

    private function runRoute(Route $route, Request $request): Response
    {
        $core = function (Request $request) use ($route): Response {
            $handler = $route->handler();

            if (is_array($handler)) {
                [$class, $method] = $handler;
                $controller = is_object($class) ? $class : $this->container->make((string) $class);
                $result = $controller->{$method}($request);
            } else {
                $result = $handler($request);
            }

            return $result instanceof Response ? $result : Response::html((string) $result);
        };

        $pipeline = $core;

        foreach (array_reverse($route->middlewareList()) as $name) {
            $next = $pipeline;

            $pipeline = function (Request $request) use ($name, $next): Response {
                if ($this->middlewareResolver === null) {
                    throw new \RuntimeException('No middleware resolver configured.');
                }

                $middleware = ($this->middlewareResolver)($name);

                if (! $middleware instanceof Middleware) {
                    throw new \RuntimeException('Invalid middleware: ' . $name);
                }

                return $middleware->handle($request, $next);
            };
        }

        return $pipeline($request);
    }
}
