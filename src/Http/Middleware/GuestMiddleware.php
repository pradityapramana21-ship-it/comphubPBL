<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url(
                $this->auth->role() === \App\Enums\UserRole::Admin ? '/admin' : '/dasbor',
            ));
        }

        return $next($request);
    }
}
