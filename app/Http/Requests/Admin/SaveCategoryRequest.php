<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $categoryId = $this->route()->parameter('category')?->id;

        return [
            // Nama kategori UNIQUE di database. Aturannya ikut dicantumkan
            // di sini supaya orang melihat "nama ini sudah dipakai"
            // alih-alih halaman 500 dari pelanggaran constraint.
            'name' => [
                'required', 'string', 'min:3', 'max:80',
                Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'slug' => [
                'nullable', 'string', 'max:80', 'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Kategori dengan nama itu sudah ada.',
            'slug.unique' => 'Slug itu sudah dipakai kategori lain.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nama kategori', 'slug' => 'slug', 'description' => 'deskripsi'];
    }

    /** @return array<string, mixed> */
    public function categoryAttributes(): array
    {
        $attributes = $this->safe()->only(['name', 'description']);

        // Slug dibangkitkan dari nama kalau tidak dikirim manual, supaya
        // admin tidak perlu mengetik formatnya sendiri.
        $attributes['slug'] = Str::slug(
            (string) ($this->input('slug') ?: $this->input('name')),
        );

        if ($this->has('is_active')) {
            $attributes['is_active'] = $this->boolean('is_active');
        }

        return $attributes;
    }
}
