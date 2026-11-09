<?php

declare(strict_types=1);

// Router script for `php -S`: let the built-in server serve real files,
// and pass every other request to the front controller.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path !== '/' && is_file(__DIR__ . '/public' . $path)) {
    return false;
}

require __DIR__ . '/public/index.php';
