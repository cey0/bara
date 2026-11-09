<?php

declare(strict_types=1);

namespace Framework\Security;

/**
 * One token per session, compared with hash_equals() on every
 * POST/PUT/PATCH/DELETE request (see Application::handle()).
 */
final class Csrf
{
    public static function token(): string
    {
        if (! isset($_SESSION['_token']) || ! is_string($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    public static function check(?string $token): bool
    {
        $sessionToken = $_SESSION['_token'] ?? null;

        return is_string($token)
            && is_string($sessionToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $token);
    }
}
