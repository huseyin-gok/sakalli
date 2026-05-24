<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use App\Middlewares\CsrfMiddleware;
use App\Repositories\CampaignRepository;
use App\Repositories\CredentialCaptureRepository;
use App\Repositories\LandingPageRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserRepository;
use App\Services\AuditLogService;
use App\Services\CampaignEmailQueueProcessor;
use App\Services\CampaignPreviewService;
use App\Services\IntegrationSettingsService;
use App\Services\LdapAuthService;

/**
 * Kampanya listesi, oluşturma, detay
 */
final class CampaignController
{
    /** @var array<string, string> */
    private const INTERACTION_LABELS = [
        '' => 'Landing şablonundaki varsayılan davranış',
        'awareness_form' => 'Bilgilendirme + geri bildirim formu',
        'awareness_noform' => 'Bilgilendirme (formsuz)',
        'credential_capture' => 'Sahte oturum açma formu',
    ];

    private const STATUS_LABELS = [
        'draft' => 'Taslak',
        'scheduled' => 'Planlandı',
        'sending' => 'Gönderiliyor',
        'completed' => 'Tamamlandı',
        'stopped' => 'Durduruldu',
    ];

    public function index(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $repo = new CampaignRepository();
        View::render('campaigns/index', [
            'title' => 'Kampanyalar',
            'campaigns' => $repo->allWithTemplate(),
            'status_labels' => self::STATUS_LABELS,
            'csrf' => CsrfMiddleware::ensureToken(),
        ]);
    }

    public function createForm(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $tRepo = new TemplateRepository();
        $templates = $tRepo->listActiveForSelect();
        $lpRepo = new LandingPageRepository();
        if ($templates === []) {
            View::render('campaigns/form', [
                'title' => 'Yeni kampanya',
                'templates' => [],
                'landing_pages' => $lpRepo->listForSelect(),
                'default_smtp_from_name' => trim((string) (IntegrationSettingsService::getSmtpEnv()['SMTP_FROM_NAME'] ?? '')),
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => 'Önce en az bir aktif şablon oluşturun.',
            ]);
            return;
        }
        View::render('campaigns/form', [
            'title' => 'Yeni kampanya',
            'templates' => $templates,
            'landing_pages' => $lpRepo->listForSelect(),
            'default_smtp_from_name' => trim((string) (IntegrationSettingsService::getSmtpEnv()['SMTP_FROM_NAME'] ?? '')),
            'csrf' => CsrfMiddleware::ensureToken(),
            'error' => null,
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
        $templateId = (int) ($_POST['template_id'] ?? 0);
        if ($name === '' || $templateId <= 0) {
            $this->createFormWithError('Kampanya adı ve şablon zorunludur.');

            return;
        }
        $tRepo = new TemplateRepository();
        $versionId = $tRepo->getLatestVersionId($templateId);
        if ($versionId === null) {
            $this->createFormWithError('Seçilen şablonun sürümü yok.');

            return;
        }
        $status = (string) ($_POST['status'] ?? 'draft');
        if (!isset(self::STATUS_LABELS[$status])) {
            $status = 'draft';
        }
        $scheduled = trim((string) ($_POST['scheduled_at'] ?? ''));
        $scheduledAt = $scheduled !== '' ? $scheduled : null;
        $batch = max(1, min(500, (int) ($_POST['send_batch_size'] ?? 50)));
        $landingPageId = (int) ($_POST['landing_page_id'] ?? 0);
        if ($landingPageId > 0) {
            $lp = (new LandingPageRepository())->find($landingPageId);
            if ($lp === null) {
                $landingPageId = 0;
            }
        }

        $repo = new CampaignRepository();
        $smtpFromName = trim((string) ($_POST['smtp_from_name'] ?? ''));
        $repo->create(
            $name,
            trim((string) ($_POST['description'] ?? '')) ?: null,
            $templateId,
            $versionId,
            $landingPageId > 0 ? $landingPageId : null,
            $status,
            $scheduledAt,
            $batch,
            current_user_id(),
            $smtpFromName !== '' ? $smtpFromName : null
        );
        header('Location: ' . url('/campaigns') . '?ok=1');
    }

    public function show(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $repo = new CampaignRepository();
        $campaign = $repo->find((int) $id);
        if ($campaign === null) {
            http_response_code(404);
            echo 'Kampanya bulunamadı';
            return;
        }
        $cid = (int) $id;
        $targetsTotal = $repo->countTargets($cid);
        $targets = $repo->targetsForCampaign($cid, 500);
        $userRepo = new UserRepository();
        $ldapOus = [];
        $ldapOuNote = '';
        $le = IntegrationSettingsService::getLdapEnv();
        $ouSearchBase = trim((string) ($le['LDAP_OU_SEARCH_BASE'] ?? ''));
        if ($ouSearchBase === '') {
            $ouSearchBase = (string) ($le['LDAP_BASE_DN'] ?? '');
        }
        $ouNameFilter = trim((string) ($le['LDAP_OU_NAME_FILTER'] ?? ''));
        try {
            $ldap = self::ldapFromAppConfig();
            if ($ldap->connect()) {
                $ldapOus = $ldap->listOrganizationalUnits(
                    $ouSearchBase !== '' ? $ouSearchBase : null,
                    500,
                    $ouNameFilter !== '' ? $ouNameFilter : null
                );
                $ldap->disconnect();
                if ($ldapOus === []) {
                    $ldapOuNote = 'LDAP üzerinde OU bulunamadı. Ayarlar → LDAP veya .env içinde arama tabanı / filtre / BASE_DN değerlerini kontrol edin.';
                }
            } else {
                $ldapOuNote = 'LDAP bağlantısı kurulamadı (servis hesabı / Ayarlar → LDAP / .env). OU listesi yüklenemedi.';
            }
        } catch (\Throwable) {
            $ldapOuNote = 'OU listesi alınırken hata oluştu.';
        }
        $interactionMode = trim((string) ($campaign['interaction_mode'] ?? ''));
        $feedbackEnabled = match ($interactionMode) {
            'awareness_form' => true,
            'awareness_noform', 'credential_capture' => false,
            default => !empty($campaign['landing_page_id']) && !empty($campaign['show_feedback_form']),
        };
        $feedbackSource = $interactionMode !== ''
            ? 'Kampanya akış modu'
            : 'Landing varsayılanı';

        View::render('campaigns/show', [
            'title' => 'Kampanya: ' . $campaign['name'],
            'campaign' => $campaign,
            'targets' => $targets,
            'targets_total' => $targetsTotal,
            'form_submissions' => $repo->formSubmissionsForCampaign($cid, 200),
            'credential_captures' => (new CredentialCaptureRepository())->listForCampaign($cid, 200),
            'status_labels' => self::STATUS_LABELS,
            'departments' => $userRepo->listDepartments(),
            'ldap_ous' => $ldapOus,
            'ldap_ou_note' => $ldapOuNote,
            'ldap_ou_search_base' => $ouSearchBase,
            'ldap_ou_name_filter' => $ouNameFilter,
            'users_picker' => $userRepo->listActiveUsersForPicker(),
            'landing_pages' => (new LandingPageRepository())->listForSelect(),
            'csrf' => CsrfMiddleware::ensureToken(),
            'interaction_labels' => self::INTERACTION_LABELS,
            'feedback_enabled' => $feedbackEnabled,
            'feedback_source' => $feedbackSource,
            'can_edit_targets' => CampaignRepository::canEditTargets((string) $campaign['status']),
            'can_start_send_queue' => CampaignRepository::canStartSendQueue((string) $campaign['status'])
                && $targetsTotal > 0,
            'default_smtp_from_name' => trim((string) (IntegrationSettingsService::getSmtpEnv()['SMTP_FROM_NAME'] ?? '')),
        ]);
    }

    /**
     * Taslak / planlı kampanyada görünen gönderen adı (From display name)
     */
    public function saveSmtpFromName(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null || !CampaignRepository::canEditTargets((string) $campaign['status'])) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=targets_locked');
            exit;
        }
        $smtpFromName = trim((string) ($_POST['smtp_from_name'] ?? ''));
        $repo->updateSmtpFromName($cid, $smtpFromName !== '' ? $smtpFromName : null);
        header('Location: ' . url('/campaigns/' . $cid) . '?from_name_saved=1');
        exit;
    }

    /**
     * Taslak / planlı kampanyada bilgilendirme (landing) şablonu ataması
     */
    public function saveLandingPage(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null || !CampaignRepository::canEditTargets((string) $campaign['status'])) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=targets_locked');
            exit;
        }
        $landingPageId = (int) ($_POST['landing_page_id'] ?? 0);
        if ($landingPageId > 0 && (new LandingPageRepository())->find($landingPageId) === null) {
            $landingPageId = 0;
        }
        $repo->updateLandingPage($cid, $landingPageId > 0 ? $landingPageId : null);
        header('Location: ' . url('/campaigns/' . $cid) . '?landing_saved=1');
        exit;
    }

    /**
     * Kampanya bazında tıklama sonrası akış davranışı.
     */
    public function saveInteractionMode(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null || !CampaignRepository::canEditTargets((string) $campaign['status'])) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=targets_locked');
            exit;
        }
        $mode = trim((string) ($_POST['interaction_mode'] ?? ''));
        if (!array_key_exists($mode, self::INTERACTION_LABELS)) {
            $mode = '';
        }
        $repo->updateInteractionMode($cid, $mode !== '' ? $mode : null);
        header('Location: ' . url('/campaigns/' . $cid) . '?interaction_saved=1');
        exit;
    }

    /**
     * Pending hedefleri email_queue’ya alır; tamamlandı/durduruldu/taslak vb. sonrası tekrar gönderim için durum sending olur.
     */
    public function startSendQueue(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null || !CampaignRepository::canStartSendQueue((string) $campaign['status'])) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=send_queue_locked');
            exit;
        }
        $n = $repo->enqueuePendingTargets($cid);
        if ($n === 0) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=no_pending_targets');
            exit;
        }
        try {
            $audit = new AuditLogService(Database::connection());
            $audit->log(current_user_id(), 'campaign_queue_enqueue', 'campaign', (string) $cid, ['queued_jobs' => $n]);
        } catch (\Throwable) {
        }
        header('Location: ' . url('/campaigns/' . $cid) . '?queued=' . $n);
        exit;
    }

    /**
     * Kampanyadaki tüm hedefleri tekrar pending yapıp hemen gönderir.
     */
    public function resendNow(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null || !CampaignRepository::canStartSendQueue((string) $campaign['status'])) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=send_queue_locked');
            exit;
        }
        if ($repo->countTargets($cid) <= 0) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=no_users');
            exit;
        }

        $reset = $repo->resetTargetsForResend($cid);
        $queued = $repo->enqueuePendingTargets($cid);
        if ($queued <= 0) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=resend_now_failed');
            exit;
        }

        $processor = new CampaignEmailQueueProcessor(
            Database::connection(),
            IntegrationSettingsService::createSmtpEmailServiceForQueue(),
            new TemplateRepository()
        );
        $processed = 0;
        $guard = 0;
        do {
            $n = $processor->processBatch(500, $cid);
            $processed += $n;
            $guard++;
            if ($guard >= 20) {
                break;
            }
        } while ($n > 0);

        try {
            $audit = new AuditLogService(Database::connection());
            $audit->log(current_user_id(), 'campaign_resend_now', 'campaign', (string) $cid, [
                'reset_targets' => $reset,
                'queued_jobs' => $queued,
                'processed_now' => $processed,
            ]);
        } catch (\Throwable) {
        }

        header('Location: ' . url('/campaigns/' . $cid) . '?resent=' . $processed . '&queued=' . $queued);
        exit;
    }

    /**
     * Hedef kullanıcıları ekle: tüm aktifler, departman veya listeden seçim
     */
    public function saveTargets(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null || !CampaignRepository::canEditTargets((string) $campaign['status'])) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=targets_locked');
            exit;
        }
        $mode = (string) ($_POST['target_mode'] ?? '');
        $userRepo = new UserRepository();
        $ids = [];
        $ouResult = null;
        if ($mode === 'all_active') {
            $ids = $userRepo->listActiveUserIds();
        } elseif ($mode === 'department') {
            $did = (int) ($_POST['department_id'] ?? 0);
            if ($did > 0) {
                $ids = $userRepo->listActiveUserIdsByDepartment($did);
            }
        } elseif ($mode === 'ldap_ou') {
            $ouDn = trim((string) ($_POST['ou_dn'] ?? ''));
            if ($ouDn === '' || !self::isOuDnAllowed($ouDn)) {
                header('Location: ' . url('/campaigns/' . $cid) . '?err=invalid_ou');
                exit;
            }
            $ldap = self::ldapFromAppConfig();
            if (!$ldap->connect()) {
                header('Location: ' . url('/campaigns/' . $cid) . '?err=ldap_down');
                exit;
            }
            $le = IntegrationSettingsService::getLdapEnv();
            try {
                $fetchLimit = (int) ($le['LDAP_OU_FETCH_LIMIT'] ?? 10000);
                $rows = $ldap->fetchUsersForOu($ouDn, $fetchLimit);
            } finally {
                $ldap->disconnect();
            }
            $emailDomain = trim((string) ($le['LDAP_OU_TARGET_EMAIL_DOMAIN'] ?? ''));
            $roleSlug = trim((string) ($le['LDAP_OU_TARGET_ROLE'] ?? 'report_viewer'));
            if ($roleSlug === '') {
                $roleSlug = 'report_viewer';
            }
            $ouResult = $userRepo->matchOrProvisionFromLdapOuForTargets(
                $rows,
                $emailDomain !== '' ? $emailDomain : null,
                $roleSlug
            );
            $ids = $ouResult['ids'];
            if ($ids === []) {
                $pool = count($rows);
                $skipped = (int) $ouResult['skipped'];
                header(
                    'Location: ' . url('/campaigns/' . $cid)
                    . '?err=no_ldap_match&ldap_pool=' . $pool . '&skipped=' . $skipped
                );
                exit;
            }
        } elseif ($mode === 'selected') {
            $raw = $_POST['user_ids'] ?? [];
            $ids = is_array($raw) ? array_map('intval', $raw) : [];
        } else {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=target_mode');
            exit;
        }
        if ($ids === []) {
            header('Location: ' . url('/campaigns/' . $cid) . '?err=no_users');
            exit;
        }
        $added = $repo->addTargetsBulk($cid, $ids);
        try {
            $audit = new AuditLogService(Database::connection());
            $payload = [
                'mode' => $mode,
                'added' => $added,
                'pool_size' => count($ids),
            ];
            if ($mode === 'ldap_ou' && isset($ouResult)) {
                $payload['ldap_skipped'] = $ouResult['skipped'] ?? 0;
            }
            $audit->log(current_user_id(), 'campaign_targets_add', 'campaign', (string) $cid, $payload);
        } catch (\Throwable) {
        }
        header('Location: ' . url('/campaigns/' . $cid) . '?added=' . $added);
        exit;
    }

    /**
     * Taslak/planlı kampanyada tüm hedefleri siler (yeniden seçim için)
     */
    public function clearTargets(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        if (($_POST['confirm_clear'] ?? '') !== '1') {
            header('Location: ' . url('/campaigns/' . (int) $id) . '?err=confirm');
            exit;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null) {
            http_response_code(404);
            return;
        }
        $removed = $repo->clearTargets($cid);
        try {
            $audit = new AuditLogService(Database::connection());
            $audit->log(current_user_id(), 'campaign_targets_clear', 'campaign', (string) $cid, ['removed' => $removed]);
        } catch (\Throwable) {
        }
        header('Location: ' . url('/campaigns/' . $cid) . '?cleared=' . $removed);
        exit;
    }

    /**
     * Gönderim öncesi e-posta + tıklama akışı önizleme (panel)
     */
    public function preview(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $cid = (int) $id;
        $data = $this->loadPreviewData($cid);
        if ($data === null) {
            http_response_code(404);
            echo 'Kampanya bulunamadı veya şablon sürümü eksik.';
            return;
        }
        View::render('campaigns/preview', [
            'title' => 'Önizleme: ' . ($data['campaign']['name'] ?? ''),
            'campaign_id' => $cid,
            'preview' => $data,
            'status_labels' => self::STATUS_LABELS,
            'interaction_labels' => self::INTERACTION_LABELS,
        ]);
    }

    public function previewEmail(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $data = $this->loadPreviewData((int) $id);
        if ($data === null) {
            http_response_code(404);
            echo 'Önizleme yok';
            return;
        }
        $email = $data['email'];
        $from = htmlspecialchars((string) $email['from_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $addr = htmlspecialchars((string) $email['from_address'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subj = htmlspecialchars((string) $email['subject'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $to = htmlspecialchars((string) $email['to_example'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>E-posta önizleme</title></head><body style="margin:0;background:#f8f9fa;font-family:system-ui,sans-serif;">'
            . '<div style="padding:10px 16px;background:#ffc107;color:#333;font-size:13px;border-bottom:1px solid #e0a800;">'
            . '<strong>Önizleme</strong> — Gönderilmez; örnek alıcı: ' . $to . '</div>'
            . '<div style="padding:12px 16px;background:#fff;border-bottom:1px solid #dee2e6;font-size:14px;">'
            . '<div><strong>Konu:</strong> ' . $subj . '</div>'
            . '<div style="margin-top:6px;"><strong>Gönderen:</strong> ' . $from . ' &lt;' . $addr . '&gt;</div>'
            . (!empty($email['has_open_pixel'])
                ? '<div style="margin-top:6px;font-size:12px;color:#6c757d;">Açılış pikseli gerçek gönderimde eklenecek.</div>'
                : '')
            . '</div><div style="background:#fff;">' . (string) $email['body_html'] . '</div></body></html>';
        exit;
    }

    public function previewFlow(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $cid = (int) $id;
        $data = $this->loadPreviewData($cid);
        if ($data === null) {
            http_response_code(404);
            echo 'Önizleme yok';
            return;
        }
        $flow = $data['flow'];
        $previewMode = true;
        $token = 'ONIZLEME-' . $cid;
        if (($flow['type'] ?? '') === 'credential_capture') {
            $pageTitle = (string) ($flow['page_title'] ?? 'Oturum açın');
            $bodyHtml = (string) ($flow['body_html'] ?? '');
            $credentialsPostUrl = url('/campaigns/' . $cid . '/preview/flow');
            $previewThanksUrl = url('/campaigns/' . $cid . '/preview/thanks');
            require dirname(__DIR__, 2) . '/resources/views/landing/credential_landing.php';
            return;
        }
        $pageTitle = (string) ($flow['page_title'] ?? '');
        $bodyHtml = (string) ($flow['body_html'] ?? '');
        $showFeedbackForm = !empty($flow['show_feedback_form']);
        $feedbackPostUrl = url('/track/feedback');
        require dirname(__DIR__, 2) . '/resources/views/landing/awareness.php';
    }

    public function previewThanks(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $data = $this->loadPreviewData((int) $id);
        if ($data === null || ($data['flow']['type'] ?? '') !== 'credential_capture') {
            http_response_code(404);
            echo 'Bu akış için teşekkür önizlemesi yok';
            return;
        }
        $previewMode = true;
        require dirname(__DIR__, 2) . '/resources/views/landing/credential_thanks.php';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPreviewData(int $campaignId): ?array
    {
        return (new CampaignPreviewService(Database::connection()))->assemble($campaignId, current_user_id());
    }

    public function delete(string $id): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        $cid = (int) $id;
        $repo = new CampaignRepository();
        $campaign = $repo->find($cid);
        if ($campaign === null) {
            header('Location: ' . url('/campaigns') . '?err=not_found');
            return;
        }
        $deleted = $repo->deleteCampaign($cid);
        if ($deleted > 0) {
            try {
                $audit = new AuditLogService(Database::connection());
                $audit->log(current_user_id(), 'campaign_delete', 'campaign', (string) $cid, null);
            } catch (\Throwable) {
            }
            header('Location: ' . url('/campaigns') . '?deleted=1');
            return;
        }
        header('Location: ' . url('/campaigns') . '?err=delete');
    }

    private static function ldapFromAppConfig(): LdapAuthService
    {
        return IntegrationSettingsService::createLdapAuthService();
    }

    /**
     * Güvenlik: yalnızca LDAP_BASE_DN altındaki OU seçilebilsin
     */
    private static function isOuDnAllowed(string $dn): bool
    {
        if ($dn === '' || str_contains($dn, "\0") || str_contains($dn, '*')) {
            return false;
        }
        $base = strtolower(trim(IntegrationSettingsService::getLdapEnv()['LDAP_BASE_DN'] ?? ''));
        $d = strtolower(trim($dn));
        if ($base === '') {
            return false;
        }

        return str_ends_with($d, $base);
    }

    private function createFormWithError(string $error): void
    {
        $tRepo = new TemplateRepository();
        View::render('campaigns/form', [
            'title' => 'Yeni kampanya',
            'templates' => $tRepo->listActiveForSelect(),
            'landing_pages' => (new LandingPageRepository())->listForSelect(),
            'default_smtp_from_name' => trim((string) (IntegrationSettingsService::getSmtpEnv()['SMTP_FROM_NAME'] ?? '')),
            'csrf' => CsrfMiddleware::ensureToken(),
            'error' => $error,
        ]);
    }
}
