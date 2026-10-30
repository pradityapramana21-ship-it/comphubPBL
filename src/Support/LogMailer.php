<?php

declare(strict_types=1);

namespace App\Support;

final class LogMailer implements Mailer
{
    public function __construct(private readonly string $path)
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
    }

    public function send(string $to, string $subject, string $body): void
    {
        $block = '[' . date('c') . ']' . PHP_EOL
            . 'To: ' . $to . PHP_EOL
            . 'Subject: ' . $subject . PHP_EOL
            . $body . PHP_EOL
            . str_repeat('-', 60) . PHP_EOL;

        file_put_contents($this->path, $block, FILE_APPEND | LOCK_EX);
    }
}
