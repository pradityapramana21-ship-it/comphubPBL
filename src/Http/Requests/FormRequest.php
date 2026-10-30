<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\ValidationException;
use App\Core\Validator;

abstract class FormRequest
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    public function __construct(protected readonly Request $request)
    {
    }

    /** @return array<string, mixed> */
    abstract public function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validate(): array
    {
        if (! $this->authorize()) {
            throw HttpException::forbidden();
        }

        $validator = new Validator(
            $this->request->all(),
            $this->request->files(),
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        $validated = $validator->validate();
        $this->after($validator);

        $this->errors = $validator->errors();

        if ($this->errors !== []) {
            throw new ValidationException($this->errors, $this->oldInput());
        }

        return $validated;
    }

    protected function after(Validator $validator): void
    {
    }

    /** @return array<string, mixed> */
    protected function oldInput(): array
    {
        $old = $this->request->all();
        unset($old['_token'], $old['password'], $old['password_confirmation'], $old['current_password']);

        return $old;
    }
}
