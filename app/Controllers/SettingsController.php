<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use App\Middlewares\CsrfMiddleware;
use App\Services\AppBrandingService;
use App\Services\AwarenessContentService;
use App\Services\BrandingLogoUploadService;
use App\Services\LandingPagePresetCatalog;
use PDO;

/**
 * system_settings — hassas olmayan anahtarlar (SMTP/LDAP .env’de kalır)
 */
final class SettingsController
{
    public function index(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT `key`, value, is_secret FROM system_settings
             WHERE `key` NOT IN ('integration_ldap_v1', 'integration_smtp_v1')
             ORDER BY `key`"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        View::render('settings/index', [
            'title' => 'Sistem ayarları',
            'settings' => $rows,
            'csrf' => CsrfMiddleware::ensureToken(),
            'saved' => isset($_GET['saved']),
            'awareness_saved' => isset($_GET['awareness_saved']),
            'branding_saved' => isset($_GET['branding_saved']),
            'branding_err' => isset($_GET['branding_err']) ? (string) $_GET['branding_err'] : '',
            'branding_logo_path' => AppBrandingService::rawLogoSetting(),
            'branding_logo_effective' => AppBrandingService::effectiveLogoRelative(),
            'awareness' => AwarenessContentService::getRawForForm($pdo),
            'awareness_default_title' => AwarenessContentService::defaultTitle(),
            'awareness_default_body' => AwarenessContentService::defaultBodyHtml(),
            'landing_presets_json' => LandingPagePresetCatalog::toJson(),
        ]);
    }

    public function save(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $key = trim((string) ($_POST['new_key'] ?? ''));
        $val = (string) ($_POST['new_value'] ?? '');
        if ($key !== '' && !str_contains(strtolower($key), 'password') && !str_contains(strtolower($key), 'secret')) {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO system_settings (`key`, value, is_secret) VALUES (:k, :v, 0)
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
            );
            $stmt->execute(['k' => $key, 'v' => $val]);
        }
        header('Location: ' . url('/settings') . '?saved=1');
    }

    public function saveAwareness(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $pdo = Database::connection();
        $title = trim((string) ($_POST['awareness_title'] ?? ''));
        $bodyHtml = (string) ($_POST['awareness_body_html'] ?? '');
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (`key`, value, is_secret) VALUES (:k, :v, 0)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        $stmt->execute(['k' => AwarenessContentService::SETTING_TITLE, 'v' => $title]);
        $stmt->execute(['k' => AwarenessContentService::SETTING_BODY_HTML, 'v' => $bodyHtml]);
        header('Location: ' . url('/settings') . '?awareness_saved=1');
    }

    public function saveBranding(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }

        $raw = trim((string) ($_POST['branding_logo_path'] ?? ''));
        $file = $_FILES['branding_logo'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($code !== UPLOAD_ERR_OK) {
                header('Location: ' . url('/settings') . '?branding_err=upload');
                return;
            }
            $stored = BrandingLogoUploadService::store($file);
            if ($stored === null) {
                header('Location: ' . url('/settings') . '?branding_err=invalid');
                return;
            }
            $raw = $stored;
        }

        if (mb_strlen($raw) > 512) {
            $raw = mb_substr($raw, 0, 512);
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (`key`, value, is_secret) VALUES (:k, :v, 0)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        $stmt->execute(['k' => AppBrandingService::SETTING_LOGO_PATH, 'v' => $raw]);
        header('Location: ' . url('/settings') . '?branding_saved=1');
    }
}
