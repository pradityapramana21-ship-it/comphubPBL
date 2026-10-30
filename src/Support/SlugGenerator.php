<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

final class SlugGenerator
{
    private const MAX_LENGTH = 180;

    public function __construct(private ?Database $database = null)
    {
    }

    public function unique(string $source, string $table, ?int $ignoreId = null): string
    {
        if (preg_match('/^[a-z_]+$/', $table) !== 1) {
            throw new \InvalidArgumentException('Invalid table for slug lookup.');
        }

        $base = $this->base($source);

        if ($base === '') {
            $base = 'kegiatan';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->exists($table, $candidate, $ignoreId)) {
            $candidate = $this->appendSuffix($base, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    public function base(string $source): string
    {
        $ascii = strtr($source, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
        ]);
        $slug = strtolower($ascii);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (mb_strlen($slug) > self::MAX_LENGTH) {
            $truncated = mb_substr($slug, 0, self::MAX_LENGTH);
            $lastDash = mb_strrpos($truncated, '-');

            if ($lastDash !== false && $lastDash > 20) {
                $truncated = mb_substr($truncated, 0, $lastDash);
            }

            $slug = trim($truncated, '-');
        }

        return $slug;
    }

    public function isReserved(string $slug, array $reserved = []): bool
    {
        return in_array($slug, $reserved, true);
    }

    private function exists(string $table, string $slug, ?int $ignoreId): bool
    {
        $sql = 'SELECT 1 FROM ' . $table . ' WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        return $this->database()->selectOne($sql, $params) !== null;
    }

    private function database(): Database
    {
        return $this->database ??= Database::connectFromConfig();
    }

    private function appendSuffix(string $base, int $suffix): string
    {
        $room = self::MAX_LENGTH - mb_strlen((string) $suffix) - 1;
        $trimmed = mb_substr($base, 0, max(10, $room));

        return $trimmed . '-' . $suffix;
    }
}
