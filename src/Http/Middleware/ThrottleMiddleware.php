<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Support\RateLimiter;

final class ThrottleMiddleware implements Middleware
{
    public function __construct(
        private readonly string $name,
        private readonly RateLimiter $limiter,
        private readonly Auth $auth,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $limits = (array) Config::get('security.rate_limits', []);
        $limit = (array) ($limits[$this->name] ?? $limits['global'] ?? ['attempts' => 120, 'decay' => 60]);
        $key = 'throttle:' . $this->name . ':' . $request->ip() . ':' . ($this->auth->id() ?? 'guest');

        if ($this->limiter->tooManyAttempts($key, (int) $limit['attempts'])) {
            return Response::html('Terlalu banyak permintaan. Coba lagi nanti.', 429);
        }

        $this->limiter->hit($key, (int) $limit['decay']);

        return $next($request);
    }
}
