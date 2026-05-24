<?php

declare(strict_types=1);

/**
 * Genel yardımcı fonksiyonlar
 */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Uygulama URL tabanı (WAMP: /sakalli/public). .env: APP_BASE_PATH=/sakalli/public
 * Boşsa SCRIPT_NAME üzerinden tahmin edilir (public/index.php → /sakalli/public).
 */
function app_base_path(): string
{
    $fromEnv = $_ENV['APP_BASE_PATH'] ?? '';
    if ($fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    return rtrim($dir, '/') ?: '';
}

/**
 * Site içi yol üretir: url('/login') → /sakalli/public/login
 */
function url(string $path = '/'): string
{
    $base = app_base_path();
    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base . '/';
    }
    $path = str_starts_with($path, '/') ? $path : '/' . $path;
    return $base === '' ? $path : $base . $path;
}

/**
 * public/ altındaki statik dosya (ör. images/sakalli-logo.png)
 */
function asset_url(string $relativePath): string
{
    $path = ltrim(str_replace('\\', '/', $relativePath), '/');
    $base = app_base_path();

    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * Logo img src — Ayarlar, yoksa public/images/ (sakalli-logo.png, logo.png, …)
 */
function branding_logo_url(): string
{
    return \App\Services\AppBrandingService::logoHrefForWeb();
}

/**
 * Panel sayfaları: oturum yoksa girişe yönlendirir
 */
function require_auth(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . url('/login'));
        exit;
    }
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Panelde gösterilecek ad (oturum; yoksa DB’den bir kez doldurulur)
 */
function current_user_display_name(): string
{
    $fromSession = trim((string) ($_SESSION['user_display_name'] ?? ''));
    if ($fromSession !== '') {
        return $fromSession;
    }
    $id = current_user_id();
    if ($id === null) {
        return '';
    }
    $repo = new \App\Repositories\UserRepository();
    $u = $repo->findById($id);
    if ($u === null) {
        return '';
    }
    $name = trim((string) ($u['display_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($u['email'] ?? ''));
    }
    if ($name === '') {
        $name = trim((string) ($u['username'] ?? ''));
    }
    $_SESSION['user_display_name'] = $name;
    $_SESSION['user_email'] = trim((string) ($u['email'] ?? ''));
    $_SESSION['user_username'] = trim((string) ($u['username'] ?? ''));

    return $name;
}

function current_user_email(): string
{
    $e = trim((string) ($_SESSION['user_email'] ?? ''));
    if ($e !== '') {
        return $e;
    }
    current_user_display_name();

    return trim((string) ($_SESSION['user_email'] ?? ''));
}

/**
 * Oturumdaki kullanıcının rol görünen adları (`roles.name`, alfabetik).
 *
 * @return list<string>
 */
function current_user_role_labels(): array
{
    $id = current_user_id();
    if ($id === null) {
        return [];
    }
    /** @var list<string>|null $cached */
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = (new \App\Repositories\UserRepository())->roleNamesForUser($id);

    return $cached;
}

/**
 * Oturumdaki rol slug listesi
 *
 * @return list<string>
 */
function current_user_roles(): array
{
    $r = $_SESSION['roles'] ?? null;
    if (!is_array($r)) {
        return [];
    }
    $out = [];
    foreach ($r as $slug) {
        if (is_string($slug) && $slug !== '') {
            $out[] = $slug;
        }
    }

    return $out;
}

/**
 * Rol atanmamış eski hesaplar: geriye dönük tam erişim (ilk kurulum).
 * super_admin her zaman geçer.
 *
 * @param list<string> $allowedSlugs
 */
function rbac_has_any_role(array $allowedSlugs): bool
{
    $mine = current_user_roles();
    if ($mine === []) {
        return true;
    }
    if (in_array('super_admin', $mine, true)) {
        return true;
    }

    return count(array_intersect($allowedSlugs, $mine)) > 0;
}

/**
 * @param list<string> $allowedSlugs
 */
function require_any_role(array $allowedSlugs): void
{
    require_auth();
    if (!rbac_has_any_role($allowedSlugs)) {
        http_response_code(403);
        echo 'Bu işlem için yetkiniz yok.';
        exit;
    }
}

/** Bilgilendirme geri bildirim formu: anlama seçeneği etiketi */
function feedback_understood_label(string $v): string
{
    return match ($v) {
        'yes' => 'Evet',
        'partial' => 'Kısmen',
        'no' => 'Hayır',
        default => '—',
    };
}
