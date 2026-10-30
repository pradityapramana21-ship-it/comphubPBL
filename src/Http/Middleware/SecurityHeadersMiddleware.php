<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;

final class SecurityHeadersMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-Frame-Options', 'DENY');
    }
}
