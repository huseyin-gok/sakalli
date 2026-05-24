<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Kurum adı, logo ve panel markası — önce veritabanı (Ayarlar), sonra public/images, .env
 */
final class AppBrandingService
{
    /** system_settings: public/ altı göreli yol (örn. images/logo.png) veya tam https://… URL */
    public const SETTING_LOGO_PATH = 'branding_logo_path';

    private const DEFAULT_LOGO_RELATIVE = 'images/sakalli-logo.png';

    private const IMAGE_DIR_RELATIVE = 'images';

    /** @var list<string> */
    private const DISCOVER_CANDIDATES = [
        'logo.png',
        'logo.jpg',
        'logo.jpeg',
        'logo.webp',
        'logo.gif',
        'sakalli-logo.png',
    ];

    /**
     * {{kurum_adi}} ve benzeri yerler için.
     * Öncelik: system_settings.key = kurum_adi → SMTP_FROM_NAME → 'Kurum'
     */
    public static function kurumAdi(): string
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT value FROM system_settings WHERE `key` = :k LIMIT 1');
            $stmt->execute(['k' => 'kurum_adi']);
            $v = $stmt->fetchColumn();
            if (is_string($v)) {
                $t = trim($v);
                if ($t !== '') {
                    return $t;
                }
            }
        } catch (\Throwable) {
        }

        $smtp = IntegrationSettingsService::getSmtpEnv();
        $fromSmtp = trim((string) ($smtp['SMTP_FROM_NAME'] ?? ''));
        if ($fromSmtp !== '') {
            return $fromSmtp;
        }

        return 'Kurum';
    }

    /**
     * Veritabanındaki ham değer (form doldurmak için).
     */
    public static function rawLogoSetting(): string
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT value FROM system_settings WHERE `key` = :k LIMIT 1');
            $stmt->execute(['k' => self::SETTING_LOGO_PATH]);
            $v = $stmt->fetchColumn();
            if (is_string($v)) {
                return trim($v);
            }
        } catch (\Throwable) {
        }

        return '';
    }

    /**
     * Uygulamanın şu an kullandığı logo yolu (public/ köküne göre).
     */
    public static function effectiveLogoRelative(): string
    {
        return self::resolveEffectiveLogoRelative();
    }

    /**
     * Panel, giriş ve genel HTML img src — göreli yol için asset_url, tam URL olduğu gibi.
     */
    public static function logoHrefForWeb(): string
    {
        $raw = self::rawLogoSetting();
        if ($raw !== '' && preg_match('#^https?://#i', $raw) === 1) {
            return $raw;
        }

        return \asset_url(self::resolveEffectiveLogoRelative());
    }

    /**
     * E-posta alt bilgisi: mutlak https URL veya public kökü + göreli yol.
     */
    public static function logoHrefForEmail(string $publicBaseUrl): string
    {
        $raw = self::rawLogoSetting();
        if (preg_match('#^https?://#i', $raw) === 1) {
            return $raw;
        }

        $path = self::resolveEffectiveLogoRelative();
        $base = rtrim($publicBaseUrl, '/');

        return $base !== '' ? $base . '/' . $path : '';
    }

    private static function resolveEffectiveLogoRelative(): string
    {
        $raw = self::rawLogoSetting();
        if ($raw !== '' && preg_match('#^https?://#i', $raw) !== 1) {
            $path = self::normalizeRelativePublicPath($raw);
            if ($path !== null && self::publicFileExists($path)) {
                return $path;
            }
        }

        return self::discoverLogoInPublicImages();
    }

    private static function discoverLogoInPublicImages(): string
    {
        $fromEnv = trim((string) ($_ENV['BRANDING_LOGO_PATH'] ?? ''));
        if ($fromEnv !== '') {
            $normalized = self::normalizeRelativePublicPath($fromEnv);
            if ($normalized !== null && self::publicFileExists($normalized)) {
                return $normalized;
            }
        }

        foreach (self::DISCOVER_CANDIDATES as $name) {
            $rel = self::IMAGE_DIR_RELATIVE . '/' . $name;
            if (self::publicFileExists($rel)) {
                return $rel;
            }
        }

        $dir = self::publicRoot() . '/' . self::IMAGE_DIR_RELATIVE;
        if (is_dir($dir)) {
            $files = scandir($dir);
            if (is_array($files)) {
                sort($files);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    if (preg_match('/\.(png|jpe?g|gif|webp)$/i', $file) === 1) {
                        return self::IMAGE_DIR_RELATIVE . '/' . $file;
                    }
                }
            }
        }

        return self::DEFAULT_LOGO_RELATIVE;
    }

    private static function normalizeRelativePublicPath(string $raw): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim($raw)), '/');
        if ($path === '' || str_contains($path, '..') || !preg_match('#^[a-zA-Z0-9._/-]+$#', $path)) {
            return null;
        }

        return $path;
    }

    private static function publicFileExists(string $relativePath): bool
    {
        $normalized = self::normalizeRelativePublicPath($relativePath);
        if ($normalized === null) {
            return false;
        }

        return is_file(self::publicRoot() . '/' . $normalized);
    }

    private static function publicRoot(): string
    {
        return dirname(__DIR__, 2) . '/public';
    }
}
