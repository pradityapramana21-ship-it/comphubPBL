<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /** @var array<string, mixed> */
    private array $files;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public function __construct(
        private readonly array $data,
        array $files = [],
        private readonly array $rules = [],
        private readonly array $messages = [],
        private readonly array $attributes = [],
        private ?Database $database = null,
    ) {
        $this->files = $files;
    }

    /** @return array<string, mixed> */
    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);
            $this->validateField($field, $rules);
        }

        if ($this->errors !== []) {
            throw new ValidationException($this->errors, $this->data);
        }

        return $this->validated;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    /** @param list<string> $rules */
    private function validateField(string $field, array $rules): void
    {
        $value = $this->data[$field] ?? $this->files[$field] ?? null;
        $isFile = $this->isFileValue($value);
        $nullable = in_array('nullable', $rules, true);
        $required = in_array('required', $rules, true);

        if ($required && $this->isEmpty($value)) {
            $this->addError($field, $this->message($field, 'required'));

            return;
        }

        if ($this->isEmpty($value)) {
            return;
        }

        $this->validated[$field] = $value;

        foreach ($rules as $rule) {
            if ($rule === '' || in_array($rule, ['required', 'nullable'], true)) {
                continue;
            }

            $name = $rule;
            $parameter = null;

            if (str_contains($rule, ':')) {
                [$name, $parameter] = explode(':', $rule, 2);
            }

            $this->apply($field, $value, $name, $parameter, $isFile);
        }
    }

    private function apply(string $field, mixed $value, string $rule, ?string $parameter, bool $isFile): void
    {
        $value = is_string($value) ? trim($value) : $value;
        $string = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');

        switch ($rule) {
            case 'string':
                if (! is_string($value)) {
                    $this->addError($field, $this->message($field, 'string'));
                }
                break;
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, $this->message($field, 'integer'));
                }
                break;
            case 'numeric':
                if (! is_numeric($value)) {
                    $this->addError($field, $this->message($field, 'numeric'));
                }
                break;
            case 'boolean':
                if (! in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false', 'on'], true)) {
                    $this->addError($field, $this->message($field, 'boolean'));
                }
                break;
            case 'email':
                if (filter_var($string, FILTER_VALIDATE_EMAIL) === false) {
                    $this->addError($field, $this->message($field, 'email'));
                }
                break;
            case 'url':
                if (! $this->isHttpUrl($string)) {
                    $this->addError($field, $this->message($field, 'url'));
                }
                break;
            case 'date':
                if (strtotime($string) === false) {
                    $this->addError($field, $this->message($field, 'date'));
                }
                break;
            case 'after':
                $this->compareDate($field, $string, (string) $parameter, true, false);
                break;
            case 'after_or_equal':
                $this->compareDate($field, $string, (string) $parameter, true, true);
                break;
            case 'before':
                $this->compareDate($field, $string, (string) $parameter, false, false);
                break;
            case 'before_or_equal':
                $this->compareDate($field, $string, (string) $parameter, false, true);
                break;
            case 'same':
                if (($this->data[(string) $parameter] ?? null) !== $value) {
                    $this->addError($field, $this->message($field, 'same', [
                        ':other' => $this->attribute((string) $parameter),
                    ]));
                }
                break;
            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->addError($field, $this->message($field, 'confirmed'));
                }
                break;
            case 'in':
                if (! in_array($string, explode(',', (string) $parameter), true)) {
                    $this->addError($field, $this->message($field, 'in'));
                }
                break;
            case 'min':
                $this->checkSize($field, $value, (float) $parameter, $isFile, true);
                break;
            case 'max':
                $this->checkSize($field, $value, (float) $parameter, $isFile, false);
                break;
            case 'file':
                if (! $isFile || ($value['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->addError($field, $this->message($field, 'file'));
                }
                break;
            case 'mimetypes':
                $this->checkMime($field, $value, (string) $parameter, $isFile);
                break;
            case 'unique':
                $this->checkUnique($field, $string, (string) $parameter);
                break;
            case 'exists':
                $this->checkExists($field, $string, (string) $parameter);
                break;
            default:
                throw new \LogicException('Aturan validasi tidak dikenal: ' . $rule);
        }
    }

    private function checkSize(string $field, mixed $value, float $limit, bool $isFile, bool $minimum): void
    {
        if ($isFile) {
            $sizeKb = ((int) ($value['size'] ?? 0)) / 1024;
            $passes = $minimum ? $sizeKb >= $limit : $sizeKb <= $limit;
            $message = $minimum
                ? $this->message($field, 'file_min', [':min' => $this->formatSize($limit)])
                : $this->message($field, 'file_max', [':max' => $this->formatSize($limit)]);
        } elseif (is_int($value) || is_float($value)) {
            $passes = $minimum ? (float) $value >= $limit : (float) $value <= $limit;
            $message = $this->message($field, $minimum ? 'min' : 'max', [':limit' => (string) $limit]);
        } else {
            $length = mb_strlen((string) $value);
            $passes = $minimum ? $length >= $limit : $length <= $limit;
            $message = $this->message($field, $minimum ? 'min_string' : 'max_string', [':limit' => (string) $limit]);
        }

        if (! $passes) {
            $this->addError($field, $message);
        }
    }

    private function checkMime(string $field, mixed $value, string $allowed, bool $isFile): void
    {
        if (! $isFile || ($value['tmp_name'] ?? '') === '' || ! is_file((string) $value['tmp_name'])) {
            $this->addError($field, $this->message($field, 'file'));

            return;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $value['tmp_name']);
        $extensions = [
            'pdf' => 'application/pdf',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        $valid = [];

        foreach (explode(',', $allowed) as $item) {
            $item = trim($item);
            $valid[] = $extensions[$item] ?? $item;
        }

        if (! is_string($mime) || ! in_array($mime, $valid, true)) {
            $this->addError($field, $this->message($field, 'mimetypes'));
        }
    }

    private function checkUnique(string $field, string $value, string $parameter): void
    {
        [$table, $column, $exceptId] = array_pad(explode(',', $parameter), 3, null);
        $this->assertIdentifier((string) $table);
        $this->assertIdentifier((string) $column);

        $sql = 'SELECT 1 FROM ' . $table . ' WHERE ' . $column . ' = :value';

        if ($exceptId !== null && $exceptId !== '') {
            $sql .= ' AND id <> :id';
        }

        $sql .= ' LIMIT 1';
        $params = ['value' => $value];

        if ($exceptId !== null && $exceptId !== '') {
            $params['id'] = (int) $exceptId;
        }

        if ($this->database()->selectOne($sql, $params) !== null) {
            $this->addError($field, $this->message($field, 'unique'));
        }
    }

    private function checkExists(string $field, string $value, string $parameter): void
    {
        [$table, $column] = array_pad(explode(',', $parameter), 2, 'id');
        $this->assertIdentifier((string) $table);
        $this->assertIdentifier((string) $column);

        $found = $this->database()->selectOne(
            'SELECT 1 FROM ' . $table . ' WHERE ' . $column . ' = :value LIMIT 1',
            ['value' => $value],
        );

        if ($found === null) {
            $this->addError($field, $this->message($field, 'exists'));
        }
    }

    private function compareDate(string $field, string $value, string $target, bool $after, bool $orEqual): void
    {
        $left = strtotime($value);
        $right = $target === 'now' ? time() : strtotime((string) ($this->data[$target] ?? ''));

        if ($left === false || $right === false) {
            return;
        }

        $passes = $after
            ? ($orEqual ? $left >= $right : $left > $right)
            : ($orEqual ? $left <= $right : $left < $right);

        if (! $passes) {
            $this->addError($field, $this->message($field, $after ? 'after' : 'before', [
                ':other' => $target === 'now' ? 'sekarang' : $this->attribute($target),
            ]));
        }
    }

    private function isEmpty(mixed $value): bool
    {
        if ($this->isFileValue($value)) {
            return ($value['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
                || ($value['tmp_name'] ?? '') === '';
        }

        return $value === null || $value === '' || $value === [];
    }

    private function isFileValue(mixed $value): bool
    {
        return is_array($value) && array_key_exists('error', $value) && array_key_exists('tmp_name', $value);
    }

    private function isHttpUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && is_string(parse_url($value, PHP_URL_HOST))
            && parse_url($value, PHP_URL_HOST) !== '';
    }

    private function database(): Database
    {
        return $this->database ??= Database::connectFromConfig();
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-z_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Nama tabel atau kolom tidak valid.');
        }
    }

    private function attribute(string $field): string
    {
        return $this->attributes[$field] ?? str_replace('_', ' ', $field);
    }

    /** @param array<string, string> $replace */
    private function message(string $field, string $rule, array $replace = []): string
    {
        $defaults = [
            'required' => 'Kolom :attribute wajib diisi.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'integer' => 'Kolom :attribute harus berupa angka bulat.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'boolean' => 'Kolom :attribute harus berupa ya atau tidak.',
            'email' => 'Format email pada :attribute tidak valid.',
            'url' => 'Kolom :attribute harus berupa URL http atau https yang valid.',
            'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
            'after' => 'Kolom :attribute harus setelah :other.',
            'before' => 'Kolom :attribute harus sebelum :other.',
            'same' => 'Kolom :attribute harus sama dengan :other.',
            'confirmed' => 'Konfirmasi :attribute tidak cocok.',
            'in' => 'Pilihan :attribute tidak valid.',
            'min' => 'Kolom :attribute minimal :limit.',
            'max' => 'Kolom :attribute maksimal :limit.',
            'min_string' => 'Kolom :attribute minimal :limit karakter.',
            'max_string' => 'Kolom :attribute maksimal :limit karakter.',
            'file_min' => 'Ukuran :attribute minimal :min.',
            'file_max' => 'Ukuran :attribute maksimal :max.',
            'file' => 'Kolom :attribute harus berupa berkas yang berhasil diunggah.',
            'mimetypes' => 'Tipe berkas :attribute tidak didukung.',
            'unique' => 'Nilai :attribute sudah digunakan.',
            'exists' => 'Nilai :attribute tidak ditemukan.',
        ];
        $template = $this->messages[$field . '.' . $rule]
            ?? $this->messages[$field]
            ?? $defaults[$rule]
            ?? 'Nilai :attribute tidak valid.';

        return strtr($template, [':attribute' => $this->attribute($field)] + $replace);
    }

    private function formatSize(float $kilobytes): string
    {
        return $kilobytes >= 1024
            ? rtrim(rtrim(number_format($kilobytes / 1024, 1, ',', '.'), '0'), ',') . ' MB'
            : (string) (int) $kilobytes . ' KB';
    }
}
