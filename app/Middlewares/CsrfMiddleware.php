<?php

declare(strict_types=1);

namespace App\Middlewares;

/**
 * CSRF token üretimi ve doğrulama — form POST'larında kullanın
 */
final class CsrfMiddleware
{
    public static function ensureToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf_token'])
            && hash_equals($_SESSION['_csrf_token'], $token);
    }
}
