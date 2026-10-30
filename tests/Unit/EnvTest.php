<?php

declare(strict_types=1);

use App\Core\Env;

test('env parses quoted values and comments', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($file, "A=one\nB=\"two words\"\n# comment\nC=three # tail\n");
    Env::load($file);
    unlink($file);

    assertSame('one', Env::get('A'));
    assertSame('two words', Env::get('B'));
    assertSame('three # tail', Env::get('C'));
});

test('env keeps existing non-empty values and returns defaults', function (): void {
    $first = tempnam(sys_get_temp_dir(), 'env');
    $second = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($first, "KEEP=yes\n");
    file_put_contents($second, "KEEP=no\n");
    Env::load($first);
    Env::load($second);
    unlink($first);
    unlink($second);

    assertSame('yes', Env::get('KEEP'));
    assertSame('fallback', Env::get('MISSING', 'fallback'));
});
