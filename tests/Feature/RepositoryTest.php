<?php

declare(strict_types=1);

use App\DataTransferObjects\CatalogFilter;
use App\Enums\ReportReason;
use App\Enums\UserRole;
use App\Repositories\Pdo\PdoBookmarkRepository;
use App\Repositories\Pdo\PdoCategoryRepository;
use App\Repositories\Pdo\PdoEventRepository;
use App\Repositories\Pdo\PdoProviderProfileRepository;
use App\Repositories\Pdo\PdoReportRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Services\Access;

test('repository user, provider, event, bookmark, report, and access invariants', function (): void {
    db()->transaction(function (): void {
        $users = new PdoUserRepository(db());
        $categories = new PdoCategoryRepository(db());
        $providers = new PdoProviderProfileRepository(db());
        $events = new PdoEventRepository(db());
        $bookmarks = new PdoBookmarkRepository(db());
        $reports = new PdoReportRepository(db());
        $access = new Access();

        $owner = $users->create('Pemilik', 'owner-' . uniqid() . '@example.test', password_hash('secret123', PASSWORD_DEFAULT), UserRole::Provider);
        $other = $users->create('Lain', 'other-' . uniqid() . '@example.test', password_hash('secret123', PASSWORD_DEFAULT), UserRole::Provider);
        $admin = $users->create('Admin', 'admin-' . uniqid() . '@example.test', password_hash('secret123', PASSWORD_DEFAULT), UserRole::Admin);
        $reporter = $users->create('Pelapor', 'reporter-' . uniqid() . '@example.test', password_hash('secret123', PASSWORD_DEFAULT), UserRole::User);

        assertTrue($users->emailExists($owner['email']));
        assertFalse($users->emailExists($owner['email'], (int) $owner['id']));

        $ownerProfile = $providers->create([
            'user_id' => $owner['id'],
            'organization_name' => 'Org Uji ' . uniqid(),
            'campus_name' => 'Kampus Uji',
            'official_email' => 'org-' . uniqid() . '@example.test',
        ]);
        $otherProfile = $providers->create([
            'user_id' => $other['id'],
            'organization_name' => 'Org Lain ' . uniqid(),
            'campus_name' => 'Kampus Lain',
            'official_email' => 'lain-' . uniqid() . '@example.test',
        ]);
        $providers->update((int) $ownerProfile['id'], ['verification_status' => 'verified', 'verified_by' => $admin['id']]);

        $category = $categories->findBySlug('lomba');
        assertTrue($category !== null);

        $start = (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP');
        $end = (new DateTimeImmutable('+10 days +3 hours'))->format('Y-m-d H:i:sP');
        $deadline = (new DateTimeImmutable('+5 days'))->format('Y-m-d H:i:sP');

        $approved = $events->create([
            'provider_profile_id' => $ownerProfile['id'],
            'category_id' => $category['id'],
            'title' => 'Lomba Uji ' . uniqid(),
            'slug' => 'lomba-uji-' . uniqid(),
            'description' => str_repeat('Deskripsi kegiatan uji yang jelas dan lengkap. ', 4),
            'registration_url' => 'https://example.test/daftar',
            'mode' => 'online',
            'platform_url' => 'https://example.test/zoom',
            'event_start_at' => $start,
            'event_end_at' => $end,
            'registration_deadline' => $deadline,
            'status' => 'approved',
            'published_at' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:sP'),
        ]);
        $draft = $events->create([
            'provider_profile_id' => $ownerProfile['id'],
            'category_id' => $category['id'],
            'title' => 'Draf Uji ' . uniqid(),
            'slug' => 'draf-uji-' . uniqid(),
            'description' => str_repeat('Deskripsi draf kegiatan uji yang jelas. ', 4),
            'registration_url' => 'https://example.test/draf',
            'mode' => 'offline',
            'location_text' => 'Aula',
            'event_start_at' => $start,
            'event_end_at' => $end,
            'registration_deadline' => $deadline,
            'status' => 'draft',
        ]);

        $catalog = $events->paginatePublicCatalog(new CatalogFilter(q: 'Lomba Uji'));
        assertSame(1, count($catalog->items));
        assertSame($approved['id'], $catalog->items[0]['id']);
        assertTrue($events->findApprovedBySlug((string) $approved['slug']) !== null);
        assertTrue($events->findByIdForProvider((int) $draft['id'], (int) $otherProfile['id']) === null);

        assertTrue($bookmarks->add((int) $reporter['id'], (int) $approved['id']));
        assertFalse($bookmarks->add((int) $reporter['id'], (int) $approved['id']));
        assertTrue($bookmarks->exists((int) $reporter['id'], (int) $approved['id']));
        assertSame([(int) $approved['id']], $bookmarks->eventIdsFor((int) $reporter['id'], [(int) $approved['id']]));

        $events->incrementBookmarkCount((int) $approved['id']);
        $events->decrementBookmarkCount((int) $approved['id']);
        $events->decrementBookmarkCount((int) $approved['id']);
        assertSame(0, (int) ($events->findById((int) $approved['id'])['bookmark_count'] ?? -1));

        $report = $reports->create((int) $approved['id'], (int) $reporter['id'], ReportReason::BrokenLink, 'Tautan mati.');
        assertTrue($reports->findExisting((int) $approved['id'], (int) $reporter['id']) !== null);

        try {
            db()->transaction(fn () => $reports->create((int) $approved['id'], (int) $reporter['id'], ReportReason::Spam, null));
            throw new RuntimeException('Unique report constraint expected');
        } catch (PDOException) {
        }

        $owner = $users->findById((int) $owner['id']);
        $other = $users->findById((int) $other['id']);
        $admin = $users->findById((int) $admin['id']);
        $reporter = $users->findById((int) $reporter['id']);

        assertTrue($access->canUpdateEvent($owner, $draft));
        assertFalse($access->canUpdateEvent($owner, $approved));
        assertFalse($access->canUpdateEvent($other, $draft));
        assertTrue($access->canUpdateEvent($admin, $draft));
        assertTrue($access->canModerateEvent($admin));
        assertFalse($access->canModerateEvent($owner));
        assertTrue($access->canBookmark($reporter, $approved));
        assertTrue($access->canBookmark($owner, $approved));
        assertTrue($access->canReport($reporter, $approved));
        assertFalse($access->canReport($owner, $approved));
        assertTrue($access->canViewReport($reporter, $report));
        assertTrue($access->canWithdrawReport($reporter, $report));
        assertFalse($access->canViewReport($owner, $report));
        assertTrue($access->canManageUser($admin, $owner));
        assertFalse($access->canManageUser($admin, $admin));
    });
});
