<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\CategoryRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Services\ModerationService;
use App\Support\SlugGenerator;

final class AdminEventController extends Controller
{
    public function __construct(
        View $view,
        private readonly EventRepository $events,
        private readonly CategoryRepository $categories,
        private readonly ProviderProfileRepository $providers,
        private readonly AuditLogRepository $audit,
        private readonly Auth $auth,
        private readonly ModerationService $moderation,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status');
        $category = $request->string('category');
        $mode = $request->string('mode');
        $filter = new CatalogFilter(
            q: mb_substr(trim($request->string('q')), 0, 100),
            category: preg_match('/^[a-z0-9-]{1,80}$/', $category) === 1 ? $category : '',
            mode: in_array($mode, ['online', 'offline', 'hybrid'], true) ? $mode : '',
            status: in_array($status, EventStatus::values(), true) ? $status : '',
            page: max(1, (int) $request->input('page', 1)),
        );

        return $this->renderPage('admin/events/index', [
            'title' => 'Kegiatan',
            'events' => $this->events->paginateForAdmin($filter),
            'filter' => $filter,
            'categories' => $this->categories->allActive(),
            'statuses' => EventStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->renderPage('admin/events/form', [
            'title' => 'Tambah kegiatan',
            'event' => null,
            'values' => [],
            'categories' => $this->categories->allActive(),
            'providers' => $this->providers->listForAdmin(),
            'statuses' => EventStatus::values(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validateEvent($request, null);
        $event = $this->events->create($this->attributes($data, $request, null));

        $this->audit($request, 'event.create', (int) $event['id'], [
            'title' => $event['title'],
            'status' => $event['status'],
        ]);
        app()->session()->flash('status', 'Kegiatan dibuat.');

        return $this->redirect(url('/admin/kegiatan/' . $event['id']));
    }

    public function edit(Request $request): Response
    {
        $event = $this->findEvent($request);

        return $this->renderPage('admin/events/form', [
            'title' => 'Ubah kegiatan',
            'event' => $event,
            'values' => $this->formValues($event),
            'categories' => $this->categories->allActive(),
            'providers' => $this->providers->listForAdmin(),
            'statuses' => EventStatus::values(),
        ]);
    }

    public function update(Request $request): Response
    {
        $event = $this->findEvent($request);
        $data = $this->validateEvent($request, $event);
        $updated = $this->events->update((int) $event['id'], $this->attributes($data, $request, $event));

        $this->audit($request, 'event.update', (int) $event['id'], [
            'title' => $updated['title'],
            'status' => $updated['status'],
        ]);
        app()->session()->flash('status', 'Kegiatan diperbarui.');

        return $this->redirect(url('/admin/kegiatan/' . $event['id']));
    }

    public function destroy(Request $request): Response
    {
        $event = $this->findEvent($request);
        $this->moderation->archive(
            (int) $event['id'],
            (int) $this->auth->id(),
            $request->ip(),
            $request->header('user-agent'),
        );
        app()->session()->flash('status', 'Kegiatan diarsipkan. Data dan simpanan tetap utuh.');

        return $this->redirect(url('/admin/kegiatan'));
    }

    /** @return array<string, mixed> */
    private function findEvent(Request $request): array
    {
        $id = (int) (($request->attribute('route', []))['event'] ?? 0);
        $event = $this->events->findById($id);

        if ($event === null) {
            $this->abort(404);
        }

        return $event;
    }

    /** @return array<string, mixed> */
    private function validateEvent(Request $request, ?array $event): array
    {
        $id = $event === null ? null : (int) $event['id'];
        $rules = [
            'title' => 'required|string|min:5|max:180',
            'slug' => 'nullable|string|max:200|unique:events,slug' . ($id === null ? '' : ',' . $id),
            'provider_profile_id' => 'required|integer|exists:provider_profiles,id',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'required|string|min:20|max:8000',
            'requirements' => 'nullable|string|max:3000',
            'organizer_name' => 'required|string|max:180',
            'registration_url' => 'required|url|max:500',
            'source_url' => 'required|url|max:500',
            'source_note' => 'nullable|string|max:2000',
            'source_checked_at' => 'required|date',
            'mode' => 'required|in:online,offline,hybrid',
            'location_text' => 'nullable|string|max:255',
            'platform_url' => 'nullable|url|max:500',
            'event_start_at' => 'required|date',
            'event_end_at' => 'required|date|after_or_equal:event_start_at',
            'registration_deadline' => 'required|date|before_or_equal:event_start_at',
            'status' => 'required|in:' . implode(',', EventStatus::values()),
            'rejection_reason' => 'nullable|string|max:2000',
            'event_type' => 'nullable|in:competition,webinar,seminar',
            'registration_start_at' => 'nullable|date',
            'organizer_email' => 'nullable|email|max:190',
            'contact_number' => 'nullable|string|max:40',
        ];

        $data = (new Validator($request->all(), [], $rules, [], [
            'title' => 'judul',
            'slug' => 'slug',
            'provider_profile_id' => 'penyelenggara',
            'category_id' => 'kategori',
            'description' => 'deskripsi',
            'organizer_name' => 'nama penyelenggara',
            'registration_url' => 'tautan pendaftaran',
            'source_url' => 'tautan sumber',
            'source_checked_at' => 'tanggal pemeriksaan sumber',
            'event_start_at' => 'waktu mulai',
            'event_end_at' => 'waktu selesai',
            'registration_deadline' => 'tenggat pendaftaran',
            'status' => 'status',
        ]))->validate();

        if ($data['mode'] !== 'online' && trim((string) ($data['location_text'] ?? '')) === '') {
            throw new ValidationException(
                ['location_text' => ['Lokasi wajib diisi untuk mode offline atau hybrid.']],
                $request->all(),
            );
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $existing */
    private function attributes(array $data, Request $request, ?array $existing): array
    {
        $id = $existing === null ? null : (int) $existing['id'];
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($slug === '') {
            $slug = (new SlugGenerator())->unique((string) $data['title'], 'events', $id);
        } elseif (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new ValidationException(
                ['slug' => ['Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.']],
                $request->all(),
            );
        }

        $status = (string) $data['status'];
        $publishedAt = $existing['published_at'] ?? null;

        if ($status === 'approved' && $publishedAt === null) {
            $publishedAt = $this->now();
        }

        if (in_array($status, ['draft', 'pending', 'changes_requested', 'rejected'], true)) {
            $publishedAt = null;
        }

        $archivedAt = $status === 'archived'
            ? (($existing['archived_at'] ?? null) ?: $this->now())
            : null;

        return [
            'provider_profile_id' => (int) $data['provider_profile_id'],
            'category_id' => (int) $data['category_id'],
            'title' => trim((string) $data['title']),
            'slug' => $slug,
            'description' => trim((string) $data['description']),
            'requirements' => $data['requirements'] ?? null,
            'registration_url' => (string) $data['registration_url'],
            'mode' => (string) $data['mode'],
            'location_text' => $data['location_text'] ?? null,
            'platform_url' => $data['platform_url'] ?? null,
            'event_start_at' => $this->toDbDateTime($data['event_start_at']),
            'event_end_at' => $this->toDbDateTime($data['event_end_at']),
            'registration_deadline' => $this->toDbDateTime($data['registration_deadline']),
            'deadline_confirmed' => (string) $request->input('deadline_confirmed', '0') === '1' ? 'true' : 'false',
            'status' => $status,
            'published_at' => $publishedAt,
            'archived_at' => $archivedAt,
            'rejection_reason' => $data['rejection_reason'] ?? null,
            'organizer_name' => trim((string) $data['organizer_name']),
            'source_url' => (string) $data['source_url'],
            'source_checked_at' => $this->toDbDate($data['source_checked_at']),
            'source_note' => $data['source_note'] ?? null,
            'event_type' => trim((string) ($data['event_type'] ?? '')) ?: ($existing['event_type'] ?? null),
            'registration_start_at' => trim((string) ($data['registration_start_at'] ?? '')) !== ''
                ? $this->toDbDateTime($data['registration_start_at'])
                : ($existing['registration_start_at'] ?? null),
            'organizer_email' => trim((string) ($data['organizer_email'] ?? '')) ?: ($existing['organizer_email'] ?? null),
            'contact_number' => trim((string) ($data['contact_number'] ?? '')) ?: ($existing['contact_number'] ?? null),
        ];
    }

    /** @param array<string, mixed> $event @return array<string, string> */
    private function formValues(array $event): array
    {
        return [
            'event_start_at' => $this->localDateTime($event['event_start_at'] ?? null),
            'event_end_at' => $this->localDateTime($event['event_end_at'] ?? null),
            'registration_deadline' => $this->localDateTime($event['registration_deadline'] ?? null),
            'registration_start_at' => $this->localDateTime($event['registration_start_at'] ?? null),
            'source_checked_at' => $this->localDate($event['source_checked_at'] ?? null),
        ];
    }

    private function toDbDateTime(mixed $value): string
    {
        return (new \DateTimeImmutable(trim((string) $value), new \DateTimeZone('Asia/Jakarta')))
            ->format('Y-m-d H:i:sP');
    }

    private function toDbDate(mixed $value): string
    {
        return (new \DateTimeImmutable(trim((string) $value), new \DateTimeZone('Asia/Jakarta')))
            ->setTime(0, 0)
            ->format('Y-m-d H:i:sP');
    }

    private function localDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (new \DateTimeImmutable((string) $value))
            ->setTimezone(new \DateTimeZone('Asia/Jakarta'))
            ->format('Y-m-d\TH:i');
    }

    private function localDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (new \DateTimeImmutable((string) $value))
            ->setTimezone(new \DateTimeZone('Asia/Jakarta'))
            ->format('Y-m-d');
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))
            ->format('Y-m-d H:i:sP');
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, int $targetId, array $metadata): void
    {
        $this->audit->record(
            $this->auth->id(),
            UserRole::Admin->value,
            $action,
            'event',
            $targetId,
            $metadata,
            $request->ip(),
            $request->header('user-agent'),
        );
    }
}
