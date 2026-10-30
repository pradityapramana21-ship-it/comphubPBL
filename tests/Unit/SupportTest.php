<?php

declare(strict_types=1);

use App\Core\Paginator;
use App\Support\DateHelper;
use App\Support\SlugGenerator;
use App\Support\TextNormalizer;

test('date helper formats ranges and relative days in WIB', function (): void {
    $dates = new DateHelper();
    $start = new DateTimeImmutable('2026-05-10 09:00:00', new DateTimeZone('Asia/Jakarta'));

    assertSame('10 Mei 2026, 09.00-14.30', $dates->range(
        $start,
        new DateTimeImmutable('2026-05-10 14:30:00', new DateTimeZone('Asia/Jakarta')),
    ));
    assertSame('10-11 Mei 2026', $dates->range(
        $start,
        new DateTimeImmutable('2026-05-11 14:30:00', new DateTimeZone('Asia/Jakarta')),
    ));
    assertSame('1 Jan 2026, 07.30', $dates->dateTime(
        new DateTimeImmutable('2026-01-01 00:30:00', new DateTimeZone('UTC')),
    ));
    assertSame('5 hari lagi', $dates->relative(
        (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->modify('+5 days'),
    ));
});

test('normaliser catches leet, repeated letters, and spaced evasion', function (): void {
    $normaliser = new TextNormalizer();

    assertSame('gratis', $normaliser->normaliseAggressive('gr4t1s'));
    assertSame('judol', $normaliser->normaliseAggressive('juuudolll'));
    assertSame('lomba rohani', $normaliser->normalise('Lomba---Rohani!!!'));
    assertTrue($normaliser->contains('G R A T I S untuk semua', 'gratis'));
    assertTrue($normaliser->contains('Promo GR4T1S', 'gratis'));
    assertSame(3, $normaliser->countPhrase('Gratis, GR4T1S, gratis', 'gratis'));
    assertFalse($normaliser->contains('kegiatan biasa', 'judol'));
});

test('slug generator truncates at word boundary and suffixes collisions', function (): void {
    $slugs = new SlugGenerator();
    $base = $slugs->base(str_repeat('kegiatan lomba ', 20));

    assertTrue(mb_strlen($base) <= 180);
    assertFalse(str_ends_with($base, '-'));

    db()->execute("DELETE FROM categories WHERE slug LIKE 'uji-slug%'");
    db()->execute("INSERT INTO categories (name, slug) VALUES ('Uji', 'uji-slug')");
    db()->execute("INSERT INTO categories (name, slug) VALUES ('Uji 2', 'uji-slug-2')");

    try {
        assertSame('uji-slug-3', $slugs->unique('Uji Slug', 'categories'));
    } finally {
        db()->execute("DELETE FROM categories WHERE slug LIKE 'uji-slug%'");
    }
});

test('filter whitelists query values and paginator preserves them', function (): void {
    $request = App\Core\Request::create('GET', '/kegiatan?q=lomba&category=webinar&mode=hack&status=all&sort=undur&page=2');
    $filter = App\DataTransferObjects\CatalogFilter::fromRequest($request);

    assertSame('lomba', $filter->q);
    assertSame('webinar', $filter->category);
    assertSame('', $filter->mode);
    assertSame('open', $filter->status);
    assertSame('newest', $filter->sort);
    assertSame(2, $filter->page);

    $paginator = new Paginator([], 45, 10, 2, '/kegiatan', ['q' => 'lomba']);
    assertSame(5, $paginator->lastPage());
    assertSame('/kegiatan?q=lomba', $paginator->url(1));
    assertSame('/kegiatan?q=lomba&page=3', $paginator->url(3));
});
