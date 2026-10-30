<?php

declare(strict_types=1);

use App\Core\ValidationException;
use App\Core\Validator;

test('validator accepts valid cross-field data', function (): void {
    $validator = new Validator([
        'email' => 'baru@example.test',
        'start_date' => '2026-11-01 09:00',
        'end_date' => '2026-11-01 15:00',
        'mode' => 'offline',
        'location' => 'Aula Kampus',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ], [], [
        'email' => 'required|email|max:255',
        'start_date' => 'required|date|after:now',
        'end_date' => 'required|date|after:start_date',
        'mode' => 'required|in:online,offline,hybrid',
        'location' => 'required|string|max:255',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $result = $validator->validate();

    assertSame('baru@example.test', $result['email']);
});

test('validator reports cross-field and format failures', function (): void {
    $validator = new Validator([
        'email' => 'not-an-email',
        'start_date' => '2020-01-01 09:00',
        'end_date' => '2019-01-01 09:00',
        'mode' => 'offline',
        'location' => '',
        'password' => 'short',
        'password_confirmation' => 'different',
    ], [], [
        'email' => 'required|email',
        'start_date' => 'required|date|after:now',
        'end_date' => 'required|date|after:start_date',
        'mode' => 'required|in:online,offline,hybrid',
        'location' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    try {
        $validator->validate();
        throw new RuntimeException('ValidationException expected');
    } catch (ValidationException $exception) {
        $errors = $exception->errors();
    }

    assertTrue(isset($errors['email']));
    assertTrue(isset($errors['start_date']));
    assertTrue(isset($errors['end_date']));
    assertTrue(isset($errors['location']));
    assertTrue(isset($errors['password']));
    assertFalse(isset($errors['mode']));
});

test('validator unique rule ignores the current record', function (): void {
    db()->execute("INSERT INTO categories (name, slug) VALUES ('Validasi', 'validasi-uji') ON CONFLICT (slug) DO NOTHING");
    db()->execute("DELETE FROM categories WHERE slug = 'validasi-uji-2'");
    db()->execute("INSERT INTO categories (name, slug) VALUES ('Validasi 2', 'validasi-uji-2')");

    try {
        $id = (int) db()->selectOne("SELECT id FROM categories WHERE slug = 'validasi-uji'")['id'];
        $validator = new Validator(['slug' => 'validasi-uji'], [], [
            'slug' => 'required|string|unique:categories,slug,' . $id,
        ]);
        assertSame('validasi-uji', $validator->validate()['slug']);

        $duplicate = new Validator(['slug' => 'validasi-uji-2'], [], [
            'slug' => 'required|string|unique:categories,slug,' . $id,
        ]);

        try {
            $duplicate->validate();
            throw new RuntimeException('ValidationException expected');
        } catch (ValidationException $exception) {
            assertTrue(isset($exception->errors()['slug']));
        }
    } finally {
        db()->execute("DELETE FROM categories WHERE slug IN ('validasi-uji', 'validasi-uji-2')");
    }
});


test('validator treats datetime-local values in the app timezone', function (): void {
    $pastWib = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))
        ->modify('-2 hours')
        ->format('Y-m-d\TH:i');
    $validator = new Validator(['start' => $pastWib], [], [
        'start' => 'required|date|after:now',
    ]);

    $exception = assertThrows(ValidationException::class, fn () => $validator->validate());
    assertTrue(isset($exception->errors()['start']));
});
