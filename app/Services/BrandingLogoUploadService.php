<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ayarlar sayfasından logo dosyası — public/uploads/branding/ altına güvenli isimle yazar.
 */
final class BrandingLogoUploadService
{
    private const MAX_BYTES = 2097152; // 2 MiB

    /** @var array<string, string> mime => uzantı */
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const RELATIVE_DIR = 'uploads/branding';

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file $_FILES tek girdi
     * @return non-empty-string|null Başarılıysa public/ köküne göre yol (örn. uploads/branding/logo_ab12cd34.png)
     */
    public static function store(array $file): ?string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return null;
        }

        $mime = self::detectMime($tmp);
        if ($mime === null || !isset(self::MIME_TO_EXT[$mime])) {
            return null;
        }
        $ext = self::MIME_TO_EXT[$mime];

        $root = dirname(__DIR__, 2) . '/public';
        $dir = $root . '/' . self::RELATIVE_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return null;
        }

        $basename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dir . '/' . $basename;
        if (!@move_uploaded_file($tmp, $destAbs)) {
            return null;
        }
        @chmod($destAbs, 0644);

        return self::RELATIVE_DIR . '/' . $basename;
    }

    private static function detectMime(string $path): ?string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $m = finfo_file($f, $path);
                finfo_close($f);
                if (is_string($m) && $m !== '') {
                    return strtolower($m);
                }
            }
        }

        return null;
    }
}
