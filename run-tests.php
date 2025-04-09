<?php

putenv('DB_DSN=' . ($argv[1] ?? 'sqlite::memory:'));
putenv('DB_USERNAME=' . ($argv[2] ?? ''));
putenv('DB_PASSWORD=' . ($argv[3] ?? ''));

// Determine the correct PHPUnit executable based on the OS
$phpunit = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
    ? 'vendor\\bin\\phpunit.bat tests'
    : './vendor/bin/phpunit tests';

// Run PHPUnit
passthru($phpunit);
