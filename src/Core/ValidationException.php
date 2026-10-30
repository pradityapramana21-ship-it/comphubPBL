<?php

declare(strict_types=1);

namespace App\Core;

final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $old
     */
    public function __construct(
        private readonly array $errors,
        private readonly array $old = [],
    ) {
        parent::__construct('Data tidak valid.');
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function old(): array
    {
        return $this->old;
    }

    public function first(string $key): ?string
    {
        return $this->errors[$key][0] ?? null;
    }
}
