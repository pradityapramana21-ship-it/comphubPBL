<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\Contracts\EventRepository;

final class RevisionTokenService
{
    public function __construct(private readonly EventRepository $events)
    {
    }

    public function issue(int $eventId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $ttl = (int) Config::get('security.revision_token_ttl_days', 14);
        $expires = (new \DateTimeImmutable('now'))->modify('+' . max(1, $ttl) . ' days');

        $this->events->setRevisionToken($eventId, hash('sha256', $token), $expires->format('Y-m-d H:i:sP'));

        return $token;
    }

    /** @return array<string, mixed>|null */
    public function resolve(string $token): ?array
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        return $this->events->findRevisionEventByTokenHash(hash('sha256', $token));
    }

    public function markUsed(int $eventId): void
    {
        $this->events->markRevisionTokenUsed($eventId);
    }

    public function revoke(int $eventId): void
    {
        $this->events->revokeRevisionToken($eventId);
    }
}
