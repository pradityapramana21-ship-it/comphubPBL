@extends('layouts.admin')

@section('title', 'Verifikasi Organisasi')
@section('heading', 'Verifikasi Organisasi')
@section('subheading', 'Organisasi yang terverifikasi boleh menerbitkan kegiatan. Yang ditolak selalu disertai alasan.')

@section('content')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card__body">
                <h2 class="h6 mb-3">Menunggu keputusan ({{ $queue->total() }})</h2>
                <p class="small text-muted-soft mb-3">Paling lama menunggu di atas.</p>

                @if ($queue->isEmpty())
                    <p class="text-muted-soft mb-0">Antrean kosong.</p>
                @else
                    <div class="stack-2">
                        @foreach ($queue as $provider)
                            <div class="p-2 border rounded">
                                <div class="fw-semibold small">{{ $provider->organization_name }}</div>
                                <div class="small text-muted-soft mb-2">{{ $provider->campus_name }}</div>

                                <form method="POST" action="{{ route('admin.providers.decide', $provider) }}"
                                      data-confirm="Yakin memverifikasi organisasi ini?">
                                    @csrf
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="decision" value="verify"
                                                class="btn btn-sm btn-success flex-fill">Verifikasi</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-modal-open="tolak-{{ $provider->id }}">Tolak</button>
                                    </div>
                                </form>

                                @include('partials.confirm-modal', [
                                    'id' => 'tolak-'.$provider->id,
                                    'title' => 'Tolak verifikasi?',
                                    'body' => 'Alasan wajib diisi. Penyedia membacanya dan bisa memperbaiki.',
                                    'confirmLabel' => 'Kirim penolakan',
                                    'formId' => 'form-tolak-'.$provider->id,
                                ])
                            </div>

                            {{-- Form penolakan: alasannya wajib, jadi dialog
                                 konfirmasi biasa tidak cukup. --}}
                            <form method="POST" action="{{ route('admin.providers.decide', $provider) }}"
                                  class="mt-2" id="form-tolak-{{ $provider->id }}">
                                @csrf
                                <input type="hidden" name="decision" value="reject">
                                <div class="input-group input-group-sm">
                                    <label class="visually-hidden" for="alasan-{{ $provider->id }}">Alasan penolakan</label>
                                    <input type="text" id="alasan-{{ $provider->id }}" name="reason"
                                           class="form-control" placeholder="Alasan penolakan (wajib)"
                                           minlength="10" required>
                                    <button type="submit" class="btn btn-outline-danger">Kirim</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <form method="GET" action="{{ route('admin.providers.index') }}" class="mb-3">
            <div class="input-group">
                <label class="visually-hidden" for="q">Cari organisasi</label>
                <input type="search" id="q" name="q" class="form-control"
                       value="{{ $keyword }}" placeholder="Cari organisasi atau kampus&hellip;">
                <button type="submit" class="btn btn-outline-primary">Cari</button>
            </div>
        </form>

        <div class="row g-2 mb-3">
            @foreach (\App\Enums\VerificationStatus::cases() as $status)
                <div class="col-4">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="badge-soft badge-soft--{{ $status->tone() }}">{{ $status->label() }}</span>
                        <span class="small">{{ $perStatus[$status->value] ?? 0 }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Daftar organisasi dan status verifikasinya</caption>
                    <thead>
                        <tr>
                            <th scope="col">Organisasi</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($all as $provider)
                            <tr>
                                <td>
                                    <span class="small fw-semibold d-block">{{ $provider->organization_name }}</span>
                                    <span class="small text-muted-soft">{{ $provider->campus_name }}</span>
                                </td>
                                <td>
                                    <span class="badge-soft badge-soft--{{ $provider->verification_status->tone() }}">
                                        {{ $provider->verification_status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted-soft py-4">Tidak ada organisasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.pagination', ['paginator' => $all])
    </div>
</div>
@endsection
