<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use App\Middlewares\CsrfMiddleware;
use App\Repositories\TemplateRepository;
use App\Services\AuditLogService;
use App\Services\IntegrationSettingsService;
use App\Services\TemplateDemoDataService;
use App\Services\TemplateVariableRenderer;

/**
 * E-posta şablonları CRUD
 */
final class TemplateController
{
    /** @var array<string, string> */
    private const CATEGORIES = [
        'kargo' => 'Kargo bildirimi',
        'sifre_suresi' => 'Şifre süresi',
        'ik_duyuru' => 'İK duyurusu',
        'bt_destek' => 'BT destek',
        'toplanti_dokuman' => 'Toplantı / doküman',
        'finans_kredi' => 'Finans / kredi teklifi',
        'banka_guvenlik' => 'Banka güvenlik uyarısı',
        'vergi_odeme' => 'Vergi / ödeme bildirimi',
        'e_imza_belge' => 'E-imza / belge onayı',
        'genel_duyuru' => 'Genel duyuru',
        'other' => 'Diğer',
    ];

    public function index(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $repo = new TemplateRepository();
        View::render('templates/index', [
            'title' => 'Şablonlar',
            'templates' => $repo->allWithLatestVersion(),
            'categories' => self::CATEGORIES,
            'csrf' => CsrfMiddleware::ensureToken(),
        ]);
    }

    public function createForm(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        View::render('templates/form', [
            'title' => 'Yeni şablon',
            'categories' => self::CATEGORIES,
            'tpl' => null,
            'csrf' => CsrfMiddleware::ensureToken(),
            'error' => null,
            'email_presets' => self::loadEmailPresets(),
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
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = (string) ($_POST['category'] ?? 'other');
        if (!array_key_exists($category, self::CATEGORIES)) {
            $category = 'other';
        }
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $bodyHtml = trim((string) ($_POST['body_html'] ?? ''));
        $bodyPlain = (string) ($_POST['body_plain'] ?? '');
        if ($name === '' || $subject === '' || $bodyHtml === '') {
            View::render('templates/form', [
                'title' => 'Yeni şablon',
                'categories' => self::CATEGORIES,
                'tpl' => null,
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => $name === '' || $subject === ''
                    ? 'Ad ve konu zorunludur.'
                    : 'HTML gövde boş. Editör içeriğini kaydedip tekrar deneyin.',
                'old' => $_POST,
                'email_presets' => self::loadEmailPresets(),
            ]);
            return;
        }
        $repo = new TemplateRepository();
        $repo->create(
            $name,
            $category,
            trim((string) ($_POST['description'] ?? '')) ?: null,
            $subject,
            $bodyHtml,
            $bodyPlain === '' ? strip_tags($bodyHtml) : $bodyPlain,
            current_user_id()
        );
        header('Location: ' . url('/templates') . '?ok=1');
    }

    public function editForm(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $repo = new TemplateRepository();
        $tpl = $repo->findWithLatestVersion((int) $id);
        if ($tpl === null) {
            http_response_code(404);
            echo 'Şablon yok';
            return;
        }
        View::render('templates/form', [
            'title' => 'Şablon düzenle',
            'categories' => self::CATEGORIES,
            'tpl' => $tpl,
            'csrf' => CsrfMiddleware::ensureToken(),
            'error' => null,
            'email_presets' => self::loadEmailPresets(),
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
        $tid = (int) $id;
        $repo = new TemplateRepository();
        if ($repo->findWithLatestVersion($tid) === null) {
            http_response_code(404);
            return;
        }
        $category = (string) ($_POST['category'] ?? 'other');
        if (!array_key_exists($category, self::CATEGORIES)) {
            $category = 'other';
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $bodyHtml = trim((string) ($_POST['body_html'] ?? ''));
        $bodyPlain = (string) ($_POST['body_plain'] ?? '');
        if ($name === '' || $subject === '' || $bodyHtml === '') {
            View::render('templates/form', [
                'title' => 'Şablon düzenle',
                'categories' => self::CATEGORIES,
                'tpl' => self::mergeTemplateWithPost($repo->findWithLatestVersion($tid), $_POST),
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => $name === '' || $subject === ''
                    ? 'Ad ve konu zorunludur.'
                    : 'HTML gövde boş. TinyMCE içeriği forma aktarılmamış olabilir; tekrar kaydedin.',
                'email_presets' => self::loadEmailPresets(),
            ]);
            return;
        }
        try {
            $repo->updateMeta(
                $tid,
                $name,
                $category,
                trim((string) ($_POST['description'] ?? '')) ?: null,
                isset($_POST['is_active'])
            );
            $repo->updateLatestVersion(
                $tid,
                $subject,
                $bodyHtml,
                $bodyPlain === '' ? strip_tags($bodyHtml) : $bodyPlain
            );
        } catch (\PDOException $e) {
            error_log('Şablon kayıt hatası: ' . $e->getMessage());
            View::render('templates/form', [
                'title' => 'Şablon düzenle',
                'categories' => self::CATEGORIES,
                'tpl' => self::mergeTemplateWithPost($repo->findWithLatestVersion($tid), $_POST),
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => 'Kayıt başarısız (veritabanı). Kategori alanı migration gerektiriyor olabilir: 009_template_category_varchar.sql',
                'email_presets' => self::loadEmailPresets(),
            ]);
            return;
        }
        header('Location: ' . url('/templates/edit/' . $tid) . '?saved=1');
    }

    /**
     * Hata sonrası formda POST verisini korur (TinyMCE içeriği kaybolmasın).
     *
     * @param array<string, mixed>|null $tpl
     * @param array<string, mixed> $post
     *
     * @return array<string, mixed>|null
     */
    private static function mergeTemplateWithPost(?array $tpl, array $post): ?array
    {
        if ($tpl === null) {
            return null;
        }
        $tpl['name'] = (string) ($post['name'] ?? $tpl['name']);
        $tpl['category'] = (string) ($post['category'] ?? $tpl['category']);
        $tpl['description'] = $post['description'] ?? $tpl['description'];
        $tpl['latest_version'] = [
            'subject' => (string) ($post['subject'] ?? ''),
            'body_html' => (string) ($post['body_html'] ?? ''),
            'body_plain' => (string) ($post['body_plain'] ?? ''),
        ];
        if (array_key_exists('is_active', $post)) {
            $tpl['is_active'] = 1;
        } elseif (isset($tpl['is_active'])) {
            $tpl['is_active'] = 0;
        }

        return $tpl;
    }

    /**
     * Kayıtlı şablonun tarayıcı önizlemesi (örnek değişkenler)
     */
    public function preview(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $repo = new TemplateRepository();
        $tpl = $repo->findWithLatestVersion((int) $id);
        if ($tpl === null) {
            http_response_code(404);
            echo 'Şablon bulunamadı';
            return;
        }
        $lv = $tpl['latest_version'] ?? null;
        if (!is_array($lv)) {
            http_response_code(404);
            echo 'Şablon sürümü yok';
            return;
        }
        $vars = TemplateDemoDataService::variables(current_user_id());
        $renderer = new TemplateVariableRenderer();
        $html = $renderer->renderForEmailHtml((string) ($lv['body_html'] ?? ''), $vars);
        header('Content-Type: text/html; charset=UTF-8');
        echo self::wrapPreviewDocument($html, 'Kayıtlı şablon #' . (int) $id);
        exit;
    }

    /**
     * Editördeki taslak HTML önizlemesi (POST, yeni sekme)
     */
    public function previewDraft(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $bodyHtml = (string) ($_POST['body_html'] ?? '');
        $vars = TemplateDemoDataService::variables(current_user_id());
        $renderer = new TemplateVariableRenderer();
        $html = $renderer->renderForEmailHtml($bodyHtml, $vars);
        header('Content-Type: text/html; charset=UTF-8');
        echo self::wrapPreviewDocument($html, 'Taslak (kaydedilmemiş içerik)');
        exit;
    }

    /**
     * SMTP ile test mesajı (şablon + örnek değişkenler)
     */
    public function testEmail(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $tid = (int) $id;
        $to = trim((string) ($_POST['test_email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            header('Location: ' . url('/templates/edit/' . $tid) . '?test_err=invalid_email');
            exit;
        }
        $repo = new TemplateRepository();
        $tpl = $repo->findWithLatestVersion($tid);
        if ($tpl === null) {
            http_response_code(404);
            return;
        }
        $lv = $tpl['latest_version'] ?? null;
        if (!is_array($lv)) {
            header('Location: ' . url('/templates/edit/' . $tid) . '?test_err=no_version');
            exit;
        }
        $vars = TemplateDemoDataService::variables(current_user_id());
        $renderer = new TemplateVariableRenderer();
        $subject = $renderer->renderForEmailPlain((string) ($lv['subject'] ?? ''), $vars);
        $bodyHtml = $renderer->renderForEmailHtml((string) ($lv['body_html'] ?? ''), $vars);
        $plainSrc = (string) ($lv['body_plain'] ?? '');
        if ($plainSrc === '') {
            $plainSrc = strip_tags((string) ($lv['body_html'] ?? ''));
        }
        $bodyPlain = $renderer->renderForEmailPlain($plainSrc, $vars);

        $smtp = IntegrationSettingsService::createSmtpEmailServiceForTest(' (test)');
        $ok = $smtp->send([$to], '[Sakallı test] ' . $subject, $bodyHtml, $bodyPlain);
        if ($ok) {
            try {
                $audit = new AuditLogService(Database::connection());
                $audit->log(current_user_id(), 'template_test_email', 'template', (string) $tid, ['to' => $to]);
            } catch (\Throwable) {
            }
            header('Location: ' . url('/templates/edit/' . $tid) . '?test_sent=1');
        } else {
            header('Location: ' . url('/templates/edit/' . $tid) . '?test_err=smtp');
        }
        exit;
    }

    public function delete(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $tid = (int) $id;
        $repo = new TemplateRepository();
        if ($repo->findWithLatestVersion($tid) === null) {
            header('Location: ' . url('/templates') . '?err=not_found');
            return;
        }
        $usage = $repo->countCampaignUsage($tid);
        if ($usage > 0) {
            header('Location: ' . url('/templates') . '?err=in_use&usage=' . $usage);
            return;
        }
        $deleted = $repo->delete($tid);
        if ($deleted > 0) {
            try {
                $audit = new AuditLogService(Database::connection());
                $audit->log(current_user_id(), 'template_delete', 'template', (string) $tid, null);
            } catch (\Throwable) {
            }
            header('Location: ' . url('/templates') . '?deleted=1');
            return;
        }
        header('Location: ' . url('/templates') . '?err=delete');
    }

    private static function wrapPreviewDocument(string $bodyHtml, string $label): string
    {
        $esc = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Önizleme — Sakallı</title></head><body style="margin:0;background:#f8f9fa;">'
            . '<div style="padding:10px 16px;background:#ffc107;color:#333;font-family:system-ui,sans-serif;font-size:13px;border-bottom:1px solid #e0a800;">'
            . '<strong>Önizleme:</strong> ' . $esc . ' — Örnek değişkenler kullanıldı; gerçek gönderimde kişiselleştirilir.</div>'
            . '<div style="max-width:100%;">' . $bodyHtml . '</div></body></html>';
    }

    /**
     * @return list<array<string, string>>
     */
    private static function loadEmailPresets(): array
    {
        $path = dirname(__DIR__, 2) . '/resources/assets/email_presets.json';
        if (!is_readable($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        /** @var list<array<string, string>> $out */
        $out = [];
        foreach ($data as $row) {
            if (is_array($row) && isset($row['id'], $row['html'])) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
