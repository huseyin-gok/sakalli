<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Brute force azaltma: IP + kullanıcı adı bazlı deneme sayısı (dosya tabanlı basit örnek)
 * Üretimde Redis / veritabanı tercih edilir
 */
final class LoginRateLimiter
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $maxAttempts = 5,
        private readonly int $windowSeconds = 900
    ) {
    }

    public function isBlocked(string $key): bool
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return false;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return false;
        }
        $attempts = (int) ($data['attempts'] ?? 0);
        $first = (int) ($data['first_at'] ?? 0);
        if (time() - $first > $this->windowSeconds) {
            return false;
        }
        return $attempts >= $this->maxAttempts;
    }

    public function hit(string $key): void
    {
        $file = $this->path($key);
        $now = time();
        $data = ['attempts' => 1, 'first_at' => $now];
        if (is_file($file)) {
            $old = json_decode((string) file_get_contents($file), true);
            if (is_array($old)) {
                $first = (int) ($old['first_at'] ?? $now);
                if ($now - $first > $this->windowSeconds) {
                    $data = ['attempts' => 1, 'first_at' => $now];
                } else {
                    $data = ['attempts' => (int) ($old['attempts'] ?? 0) + 1, 'first_at' => $first];
                }
            }
        }
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0700, true);
        }
        file_put_contents($file, json_encode($data));
    }

    public function clear(string $key): void
    {
        $f = $this->path($key);
        if (is_file($f)) {
            unlink($f);
        }
    }

    private function path(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return rtrim($this->storageDir, '/\\') . '/login_' . $safe . '.json';
    }
}
