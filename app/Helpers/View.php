<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Basit view renderer — XSS için e() kaçışı
 */
final class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $path = dirname(__DIR__, 2) . '/resources/views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($path)) {
            http_response_code(500);
            echo 'View bulunamadı: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }
        require $path;
    }
}
