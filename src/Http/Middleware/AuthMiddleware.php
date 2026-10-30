<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect(url('/masuk?next=' . rawurlencode($request->path())));
        }

        return $next($request);
    }
}
