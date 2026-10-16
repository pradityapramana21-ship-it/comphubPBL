@extends('layouts.admin')

@section('title', 'Jejak Audit')
@section('heading', 'Jejak Audit')
@section('subheading', 'Hanya bisa dibaca. Setiap tindakan admin dicatat permanen; kata sandi dan token tidak pernah masuk ke sini.')

@section('content')
<form id="filter-admin" method="GET" action="{{ route('admin.audit.index') }}" class="mb-3">
    <div class="row g-2">
        <div class="col-sm-4">
            <label for="action" class="visually-hidden">Jenis tindakan</label>
            <select id="action" name="action" class="form-select">
                <option value="">Semua tindakan</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>
                        <code class="small">{{ $action }}</code>
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="visually-hidden" for="actor">ID pelaku</label>
            <input type="number" id="actor" name="actor" class="form-control"
                   value="{{ request('actor') }}" placeholder="ID pelaku" min="1">
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-outline-primary w-100">Saring</button>
        </div>
    </div>
</form>

<p class="small text-muted-soft mb-3">Total {{ number_format($total, 0, ',', '.') }} entri.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <caption class="visually-hidden">Daftar tindakan yang tercatat di jejak audit</caption>
            <thead>
                <tr>
                    <th scope="col">Waktu</th>
                    <th scope="col">Tindakan</th>
                    <th scope="col">Pelaku</th>
                    <th scope="col">Target</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $dateHelper->dateTime($log->created_at) }}</td>
                        <td><code class="small">{{ $log->action }}</code></td>
                        <td class="small">
                            @if ($log->actor_user_id)
                                #{{ $log->actor_user_id }}
                                @if ($log->actor_role)
                                    <span class="text-muted-soft">({{ $log->actor_role }})</span>
                                @endif
                            @else
                                <span class="text-muted-soft">sistem</span>
                            @endif
                        </td>
                        <td class="small text-muted-soft">{{ $log->targetLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted-soft py-4">Belum ada jejak audit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('partials.pagination', ['paginator' => $logs])
@endsection
