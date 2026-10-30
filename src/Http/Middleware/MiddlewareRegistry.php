<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Container;
use App\Core\Auth;
use App\Support\RateLimiter;

final class MiddlewareRegistry
{
    public function __construct(private readonly Container $container)
    {
    }

    public function resolve(string $name): Middleware
    {
        $parts = explode(':', $name, 2);
        $key = $parts[0];
        $argument = $parts[1] ?? null;

        return match ($key) {
            'auth' => $this->container->make(AuthMiddleware::class),
            'guest' => $this->container->make(GuestMiddleware::class),
            'role' => new RoleMiddleware($this->container->make(Auth::class), $argument ?? 'user'),
            'active' => $this->container->make(ActiveMiddleware::class),
            'csrf' => $this->container->make(CsrfMiddleware::class),
            'throttle' => new ThrottleMiddleware(
                $argument ?? 'global',
                $this->container->make(RateLimiter::class),
                $this->container->make(Auth::class),
            ),
            'security-headers' => $this->container->make(SecurityHeadersMiddleware::class),
            default => throw new \RuntimeException('Unknown middleware: ' . $name),
        };
    }
}
