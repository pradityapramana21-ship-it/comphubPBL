<?php

declare(strict_types=1);

namespace App\Support;

final class RateLimiter
{
    public function __construct(private readonly string $directory)
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $path = $this->path($key);
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new \RuntimeException('Tidak dapat membuka penyimpanan pembatas laju.');
        }

        try {
            flock($handle, LOCK_EX);
            $contents = stream_get_contents($handle);
            $data = json_decode(is_string($contents) ? $contents : '', true);
            $now = time();

            if (! is_array($data) || (int) ($data['reset'] ?? 0) <= $now) {
                $data = ['count' => 0, 'reset' => $now + $decaySeconds];
            }

            $data['count'] = (int) $data['count'] + 1;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($data));
            fflush($handle);

            return (int) $data['count'];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function attempts(string $key): int
    {
        $data = $this->read($key);

        return (int) ($data['count'] ?? 0);
    }

    public function availableIn(string $key): int
    {
        $data = $this->read($key);

        return max(0, (int) ($data['reset'] ?? 0) - time());
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    public function clear(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /** @return array{count?: int, reset?: int} */
    private function read(string $key): array
    {
        $path = $this->path($key);

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        try {
            flock($handle, LOCK_SH);
            $contents = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $data = json_decode(is_string($contents) ? $contents : '', true);

        if (! is_array($data) || (int) ($data['reset'] ?? 0) <= time()) {
            return [];
        }

        return $data;
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/') . '/' . hash('sha256', $key) . '.json';
    }
}
