<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader: Framework\ => framework/, App\ => app/.
 * Composer is intentionally not required so the whole stack stays readable.
 */
$prefixes = [
    'Framework\\' => dirname(__DIR__) . '/framework/',
    'App\\' => dirname(__DIR__) . '/app/',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $baseDir) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

require_once __DIR__ . '/../framework/Support/helpers.php';
