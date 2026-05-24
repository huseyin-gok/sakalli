<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Middlewares\CsrfMiddleware;
use App\Repositories\LandingPageRepository;
use App\Services\LandingPagePresetCatalog;

/**
 * Ayarlar — takip linki sonrası bilgilendirme şablonları (landing_pages)
 */
final class LandingPageController
{
    public function index(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $repo = new LandingPageRepository();
        View::render('settings/landing_pages/index', [
            'title' => 'Bilgilendirme şablonları',
            'pages' => $repo->allOrdered(),
            'csrf' => CsrfMiddleware::ensureToken(),
            'deleted' => isset($_GET['deleted']),
            'saved' => isset($_GET['saved']),
        ]);
    }

    public function createForm(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        View::render('settings/landing_pages/form', [
            'title' => 'Yeni bilgilendirme şablonu',
            'page' => null,
            'csrf' => CsrfMiddleware::ensureToken(),
            'error' => null,
            'landing_presets_json' => LandingPagePresetCatalog::toJson(),
        ]);
    }

    public function createSave(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $repo = new LandingPageRepository();
        $err = $this->validateAndBuild($repo, null);
        if ($err !== null) {
            View::render('settings/landing_pages/form', [
                'title' => 'Yeni bilgilendirme şablonu',
                'page' => $this->postAsPageRow(),
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => $err,
                'landing_presets_json' => LandingPagePresetCatalog::toJson(),
            ]);
            return;
        }
        $cred = isset($_POST['credential_capture']);
        $repo->create(
            trim((string) ($_POST['name'] ?? '')),
            $this->normalizeSlug((string) ($_POST['slug'] ?? '')),
            trim((string) ($_POST['page_title'] ?? '')) ?: null,
            (string) ($_POST['content_html'] ?? ''),
            !$cred && isset($_POST['show_feedback_form']),
            $cred
        );
        header('Location: ' . url('/settings/landing-pages') . '?saved=1');
    }

    public function editForm(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $repo = new LandingPageRepository();
        $page = $repo->find((int) $id);
        if ($page === null) {
            http_response_code(404);
            echo 'Şablon bulunamadı';
            return;
        }
        View::render('settings/landing_pages/form', [
            'title' => 'Şablon düzenle',
            'page' => $page,
            'csrf' => CsrfMiddleware::ensureToken(),
            'error' => null,
            'landing_presets_json' => LandingPagePresetCatalog::toJson(),
        ]);
    }

    public function editSave(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $pid = (int) $id;
        $repo = new LandingPageRepository();
        if ($repo->find($pid) === null) {
            http_response_code(404);
            return;
        }
        $err = $this->validateAndBuild($repo, $pid);
        if ($err !== null) {
            View::render('settings/landing_pages/form', [
                'title' => 'Şablon düzenle',
                'page' => array_merge($repo->find($pid) ?? [], $this->postAsPageRow()),
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => $err,
                'landing_presets_json' => LandingPagePresetCatalog::toJson(),
            ]);
            return;
        }
        $cred = isset($_POST['credential_capture']);
        $repo->update(
            $pid,
            trim((string) ($_POST['name'] ?? '')),
            $this->normalizeSlug((string) ($_POST['slug'] ?? '')),
            trim((string) ($_POST['page_title'] ?? '')) ?: null,
            (string) ($_POST['content_html'] ?? ''),
            !$cred && isset($_POST['show_feedback_form']),
            $cred
        );
        header('Location: ' . url('/settings/landing-pages') . '?saved=1');
    }

    public function delete(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $pid = (int) $id;
        $repo = new LandingPageRepository();
        if ($repo->find($pid) === null) {
            header('Location: ' . url('/settings/landing-pages'));
            return;
        }
        $repo->delete($pid);
        header('Location: ' . url('/settings/landing-pages') . '?deleted=1');
    }

    /**
     * @return array<string, mixed>
     */
    private function postAsPageRow(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'slug' => $this->normalizeSlug((string) ($_POST['slug'] ?? '')),
            'page_title' => trim((string) ($_POST['page_title'] ?? '')) ?: null,
            'content_html' => (string) ($_POST['content_html'] ?? ''),
            'show_feedback_form' => isset($_POST['show_feedback_form']) ? 1 : 0,
            'credential_capture' => isset($_POST['credential_capture']) ? 1 : 0,
        ];
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function validateAndBuild(LandingPageRepository $repo, ?int $exceptId): ?string
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = $this->normalizeSlug((string) ($_POST['slug'] ?? ''));
        $html = trim((string) ($_POST['content_html'] ?? ''));
        if ($name === '') {
            return 'Ad zorunludur.';
        }
        if ($slug === '' || strlen($slug) > 128) {
            return 'Slug zorunludur (en fazla 128 karakter, yalnızca küçük harf, rakam ve tire).';
        }
        if ($html === '' && !isset($_POST['credential_capture'])) {
            return 'HTML içerik zorunludur (kimlik yakalama modunda isteğe bağlı; yine de üst metin için hazır şablon yüklemeniz önerilir).';
        }
        if ($repo->slugExists($slug, $exceptId)) {
            return 'Bu slug başka bir şablonda kullanılıyor.';
        }

        return null;
    }
}
