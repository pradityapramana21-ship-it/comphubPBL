<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'password_reset_ttl_minutes' => 60,
    'remember_cookie' => 'comphub_remember',
    'remember_ttl_days' => 30,
    'revision_token_ttl_days' => Env::int('REVISION_TOKEN_TTL_DAYS', 14),
    'rate_limits' => [
        'login' => ['attempts' => 5, 'decay' => 900],
        'register' => ['attempts' => 5, 'decay' => 3600],
        'bookmark' => ['attempts' => 30, 'decay' => 60],
        'report' => ['attempts' => 5, 'decay' => 3600],
        'event-submit' => ['attempts' => 5, 'decay' => 3600],
        'submission-token' => ['attempts' => 30, 'decay' => 900],
        'global' => ['attempts' => 120, 'decay' => 60],
    ],
    'session' => [
        'secure' => Env::bool('SESSION_SECURE', false),
        'same_site' => 'Lax',
    ],
];
