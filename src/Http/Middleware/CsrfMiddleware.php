<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->input('_token');

        if (! $this->csrf->verify(is_string($token) ? $token : null)) {
            throw new HttpException(419);
        }

        return $next($request);
    }
}
