@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')
@section('heading', 'Manajemen Pengguna')
@section('subheading', 'Akun administrator tidak dapat diubah dari halaman ini, supaya tidak ada satu orang yang bisa mengunci sistem.')

@section('content')
<form id="filter-admin" method="GET" action="{{ route('admin.users.index') }}" class="mb-3">
    <div class="row g-2">
        <div class="col-sm-5">
            <label for="q" class="visually-hidden">Cari pengguna</label>
            <input type="search" id="q" name="q" class="form-control" value="{{ $keyword }}"
                   placeholder="Cari nama atau email&hellip;" data-search-input>
        </div>
        <div class="col-sm-3">
            <label for="role" class="visually-hidden">Peran</label>
            <select id="role" name="role" class="form-select">
                <option value="">Semua peran</option>
                @foreach (\App\Enums\UserRole::cases() as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2">
            <label for="status" class="visually-hidden">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua status</option>
                @foreach (\App\Enums\AccountStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-outline-primary w-100">Saring</button>
        </div>
    </div>
</form>

<div class="row g-2 mb-3">
    @foreach (\App\Enums\UserRole::cases() as $role)
        <div class="col-4">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="badge-soft badge-soft--neutral">{{ $role->label() }}</span>
                <span class="small">{{ $perRole[$role->value] ?? 0 }}</span>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Daftar seluruh akun</caption>
            <thead>
                <tr>
                    <th scope="col">Akun</th>
                    <th scope="col">Peran</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <span class="d-flex align-items-center gap-2">
                                <span class="badge-soft badge-soft--neutral">{{ $user->initials() }}</span>
                                <span style="min-width: 0;">
                                    <span class="small fw-semibold d-block">{{ $user->name }}</span>
                                    <span class="small text-muted-soft">{{ $user->email }}</span>
                                </span>
                            </span>
                        </td>
                        <td><span class="small">{{ $user->role->label() }}</span></td>
                        <td>
                            @if ($user->account_status === \App\Enums\AccountStatus::Active)
                                <span class="badge-soft badge-soft--success">Aktif</span>
                            @else
                                <span class="badge-soft badge-soft--danger">Ditangguhkan</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($user->role === \App\Enums\UserRole::Admin)
                                <span class="small text-muted-soft">Tidak dapat diubah</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    @if ($user->account_status === \App\Enums\AccountStatus::Active)
                                        <input type="hidden" name="account_status" value="suspended">
                                        <input type="hidden" name="suspension_note" value="Melanggar ketentuan penggunaan layanan.">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Tangguhkan akun {{ $user->name }}?">
                                            Tangguhkan
                                        </button>
                                    @else
                                        <input type="hidden" name="account_status" value="active">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Aktifkan</button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted-soft py-4">Tidak ada akun yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('partials.pagination', ['paginator' => $users])
@endsection
