{{--
    Satu field form, lengkap dengan label, pesan bantuan, dan pesan
    kesalahan.

    Semua field wajib memakai komponen ini. Alasannya bukan hanya tampilan,
    tapi konsistensi: label selalu terhubung dengan input lewat atribut for,
    pesan kesalahan selalu punya aria-describedby, dan pesan dari server
    selalu muncul di tempat yang sama. Melewatkan komponen ini di satu
    halaman saja membuat pembaca layar kehilangan penjelasan field itu
    (Panduan WCAG).
--}}

@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'hint' => null,
    'value' => null,
    'placeholder' => null,
    'rows' => 4,
    'maxlength' => null,
    'options' => null,
    'min' => null,
    'max' => null,
    'step' => null,
])

@php
    $id = $attributes->get('id', $name);
    $old = old($name, $value);

    // Nilai enum (mis. bentuk kegiatan) harus diturunkan ke string
    //_scalar_, karena yang dibutuhkan input HTML adalah nilai mentahnya,
    // bukan objek enum.
    $old = $old instanceof \BackedEnum ? $old->value : $old;
    $hasError = $errors->has($name);
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $hasError ? $id.'-error' : null,
    ])->filter()->join(' ');
@endphp

<div class="form-field">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if ($required)
            <span class="required" aria-hidden="true">*</span>
            <span class="visually-hidden">(wajib diisi)</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}"
                  class="form-control {{ $hasError ? 'is-invalid' : '' }}"
                  rows="{{ $rows }}"
                  @if ($required) required @endif
                  @if ($maxlength) maxlength="{{ $maxlength }}" @endif
                  @if ($hint) aria-describedby="{{ $describedBy }}" @endif
                  @if ($hasError) aria-invalid="true" @endif
                  {{ $attributes }}>{{ $old }}</textarea>

    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}"
                class="form-select {{ $hasError ? 'is-invalid' : '' }}"
                @if ($required) required @endif
                @if ($hint) aria-describedby="{{ $describedBy }}" @endif
                @if ($hasError) aria-invalid="true" @endif
                {{ $attributes }}>
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options ?? [] as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $old === (string) $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>

    @elseif ($type === 'checkbox')
        <div class="form-check">
            <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1"
                   class="form-check-input {{ $hasError ? 'is-invalid' : '' }}"
                   @checked($old)
                   @if ($required) required @endif
                   @if ($hasError) aria-invalid="true" @endif
                   {{ $attributes }}>
            <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
        </div>

    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
               value="{{ $type === 'password' ? '' : $old }}"
               class="form-control {{ $hasError ? 'is-invalid' : '' }}"
               @if ($required) required @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               @if ($maxlength) maxlength="{{ $maxlength }}" @endif
               @if ($min !== null) min="{{ $min }}" @endif
               @if ($max !== null) max="{{ $max }}" @endif
               @if ($step !== null) step="{{ $step }}" @endif
               @if ($hint) aria-describedby="{{ $describedBy }}" @endif
               @if ($hasError) aria-invalid="true" @endif
               {{ $attributes }}>
    @endif

    @if ($hint)
        <p class="form-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="form-error" id="{{ $id }}-error">{{ $message }}</p>
    @enderror
</div>
