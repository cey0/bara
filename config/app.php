<?php

declare(strict_types=1);

return [
    'name' => 'Bara',

    // Show exception details on error pages. Turn off in production.
    'debug' => (getenv('APP_DEBUG') ?: 'true') === 'true',

    // Verify a CSRF token on POST/PUT/PATCH/DELETE requests.
    'csrf' => true,
];
