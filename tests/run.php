<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/Unit/*Test.php') ?: [] as $file) {
    require $file;
}

foreach (glob(__DIR__ . '/Feature/*Test.php') ?: [] as $file) {
    require $file;
}

$passed = 0;
$failed = 0;

foreach ($GLOBALS['__tests'] as [$name, $callback]) {
    try {
        $callback();
        $passed++;
        echo "PASS  {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL  {$name}\n";
        echo '      ' . $exception->getMessage() . "\n";
        echo '      at ' . $exception->getFile() . ':' . $exception->getLine() . "\n";
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
