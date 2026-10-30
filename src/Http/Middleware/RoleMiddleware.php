<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Enums\UserRole;

final class RoleMiddleware implements Middleware
{
    public function __construct(
        private readonly Auth $auth,
        private readonly string $role,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect(url('/masuk?next=' . rawurlencode($request->path())));
        }

        if ($this->auth->role() !== UserRole::tryFrom($this->role)) {
            throw new \App\Core\HttpException(403);
        }

        return $next($request);
    }
}
