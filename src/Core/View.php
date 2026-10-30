<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(private readonly string $basePath)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $file = $this->basePath . '/' . ltrim($template, '/');

        if (! str_ends_with($file, '.php')) {
            $file .= '.php';
        }

        if (! is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
