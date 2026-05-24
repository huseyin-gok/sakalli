<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Repositories\CredentialCaptureRepository;
use App\Services\AwarenessContentService;
use App\Services\CampaignTokenService;
use App\Services\RiskScoreService;
use App\Services\TrackingEventService;
use PDO;

/**
 * Genel takip uçları — tıklama, açılış pikseli (open redirect yok: sabit uygulama yolu)
 */
final class TrackingController
{
    /**
     * Benzersiz token ile tıklama kaydı ve bilgilendirme sayfasına yönlendirme
     */
    public function click(string $token): void
    {
        $pdo = Database::connection();
        $svc = new CampaignTokenService($pdo);
        $target = $svc->findTargetByToken($token);
        if ($target === null) {
            http_response_code(404);
            echo 'Geçersiz veya süresi dolmuş bağlantı.';
            return;
        }

        $te = new TrackingEventService($pdo);
        $campaignId = (int) $target['campaign_id'];
        $userId = (int) $target['user_id'];

        $stmt = $pdo->prepare(
            'SELECT c.template_id, c.landing_page_id, c.interaction_mode, lp.page_title, lp.content_html, lp.show_feedback_form, lp.credential_capture
             FROM campaigns c
             LEFT JOIN landing_pages lp ON lp.id = c.landing_page_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $campaignId]);
        $cRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $templateId = (int) ($cRow['template_id'] ?? 0);

        $te->record('link_clicked', $userId, $campaignId, $templateId, $token, ['route' => 'click']);

        (new RiskScoreService($pdo))->recalculateForUser($userId);

        $hasLp = !empty($cRow['landing_page_id']);
        $interactionMode = trim((string) ($cRow['interaction_mode'] ?? ''));
        $forceCredentialCapture = $interactionMode === 'credential_capture';
        $forceAwarenessWithForm = $interactionMode === 'awareness_form';
        $forceAwarenessNoForm = $interactionMode === 'awareness_noform';
        $useCredentialCapture = $forceCredentialCapture
            || ($interactionMode === '' && $hasLp && !empty($cRow['credential_capture']));
        if ($useCredentialCapture) {
            $pt = trim((string) ($cRow['page_title'] ?? ''));
            $pageTitle = $pt !== '' ? $pt : 'Kurumsal oturum açma';
            $bodyHtml = (string) ($cRow['content_html'] ?? '');
            $credentialsPostUrl = url('/track/credentials');
            require dirname(__DIR__, 2) . '/resources/views/landing/credential_landing.php';
            return;
        }

        $landingRow = null;
        if ($hasLp && (string) ($cRow['content_html'] ?? '') !== '') {
            $landingRow = [
                'page_title' => $cRow['page_title'],
                'content_html' => $cRow['content_html'],
            ];
        }
        $content = AwarenessContentService::resolveForDisplay($pdo, $landingRow);
        $pageTitle = $content['title'];
        $bodyHtml = $content['body_html'];

        if ($forceAwarenessNoForm) {
            $showFeedbackForm = false;
        } elseif ($forceAwarenessWithForm) {
            $showFeedbackForm = true;
        } else {
            $showFeedbackForm = !empty($cRow['show_feedback_form']) && $landingRow !== null;
        }
        $feedbackPostUrl = url('/track/feedback');
        require dirname(__DIR__, 2) . '/resources/views/landing/awareness.php';
    }

    /**
     * Sahte oturum formu — simülasyon (yalnızca credential_capture açık landing’lerde)
     */
    public function credentials(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Yalnızca POST';
            return;
        }
        $token = trim((string) ($_POST['tracking_token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo 'Eksik token';
            return;
        }
        if (trim((string) ($_POST['company'] ?? '')) !== '') {
            header('Location: ' . url('/track/credential-thanks/' . rawurlencode($token)));
            return;
        }

        $pdo = Database::connection();
        $svc = new CampaignTokenService($pdo);
        $target = $svc->findTargetByToken($token);
        if ($target === null) {
            http_response_code(404);
            echo 'Geçersiz bağlantı.';
            return;
        }

        $campaignId = (int) $target['campaign_id'];
        $userId = (int) $target['user_id'];

        $stmt = $pdo->prepare(
            'SELECT c.template_id, c.landing_page_id, c.interaction_mode, lp.credential_capture
             FROM campaigns c
             LEFT JOIN landing_pages lp ON lp.id = c.landing_page_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $campaignId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $interactionMode = trim((string) ($row['interaction_mode'] ?? ''));
        $credentialAllowed = $interactionMode === 'credential_capture'
            || ($interactionMode === '' && !empty($row['landing_page_id']) && !empty($row['credential_capture']));
        if (!$credentialAllowed) {
            http_response_code(403);
            echo 'Bu işlem bu kampanya için kapalı.';
            return;
        }

        $username = trim((string) ($_POST['sim_username'] ?? ''));
        if (mb_strlen($username) > 255) {
            $username = mb_substr($username, 0, 255);
        }
        $password = (string) ($_POST['sim_password'] ?? '');
        if (strlen($password) > 512) {
            $password = substr($password, 0, 512);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;

        (new CredentialCaptureRepository($pdo))->insert(
            $campaignId,
            $userId,
            $token,
            $username !== '' ? $username : '(boş)',
            $password !== '' ? $password : '(boş)',
            $ip,
            $ua
        );

        $templateId = (int) ($row['template_id'] ?? 0);
        $te = new TrackingEventService($pdo);
        $te->record('credentials_submitted', $userId, $campaignId, $templateId, $token, [
            'route' => 'credentials',
            'username_len' => strlen($username),
        ]);

        (new RiskScoreService($pdo))->recalculateForUser($userId);

        header('Location: ' . url('/track/credential-thanks/' . rawurlencode($token)));
    }

    public function credentialThanks(string $token): void
    {
        $raw = rawurldecode($token);
        $pdo = Database::connection();
        $svc = new CampaignTokenService($pdo);
        if ($svc->findTargetByToken($raw) === null) {
            http_response_code(404);
            echo 'Geçersiz bağlantı.';
            return;
        }
        require dirname(__DIR__, 2) . '/resources/views/landing/credential_thanks.php';
    }

    /**
     * Bilgilendirme sayfası geri bildirim gönderimi (token ile kimlik)
     */
    public function feedback(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Yalnızca POST';
            return;
        }
        $token = trim((string) ($_POST['tracking_token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo 'Eksik token';
            return;
        }
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            header('Location: ' . url('/track/thanks/' . rawurlencode($token)));
            return;
        }

        $pdo = Database::connection();
        $svc = new CampaignTokenService($pdo);
        $target = $svc->findTargetByToken($token);
        if ($target === null) {
            http_response_code(404);
            echo 'Geçersiz bağlantı.';
            return;
        }

        $campaignId = (int) $target['campaign_id'];
        $userId = (int) $target['user_id'];

        $stmt = $pdo->prepare(
            'SELECT c.template_id, c.landing_page_id, c.interaction_mode, lp.show_feedback_form
             FROM campaigns c
             LEFT JOIN landing_pages lp ON lp.id = c.landing_page_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $campaignId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $interactionMode = trim((string) ($row['interaction_mode'] ?? ''));
        $feedbackAllowed = false;
        if ($interactionMode === 'awareness_form') {
            $feedbackAllowed = true;
        } elseif ($interactionMode === 'awareness_noform' || $interactionMode === 'credential_capture') {
            $feedbackAllowed = false;
        } else {
            $feedbackAllowed = !empty($row['landing_page_id']) && !empty($row['show_feedback_form']);
        }
        if (!$feedbackAllowed) {
            // Sert 403 yerine kullanıcıyı bilgilendirme teşekkür sayfasına yönlendir.
            header('Location: ' . url('/track/thanks/' . rawurlencode($token)) . '?already=1');
            return;
        }

        $dup = $pdo->prepare(
            'SELECT 1 FROM form_submissions WHERE campaign_id = :c AND user_id = :u LIMIT 1'
        );
        $dup->execute(['c' => $campaignId, 'u' => $userId]);
        if ($dup->fetchColumn()) {
            header('Location: ' . url('/track/thanks/' . rawurlencode($token)) . '?already=1');
            return;
        }

        $comment = trim((string) ($_POST['comment'] ?? ''));
        if (mb_strlen($comment) > 4000) {
            $comment = mb_substr($comment, 0, 4000);
        }
        $understood = trim((string) ($_POST['understood'] ?? ''));
        if (!in_array($understood, ['yes', 'partial', 'no', ''], true)) {
            $understood = '';
        }

        $answers = [
            'comment' => $comment,
            'understood' => $understood,
        ];
        $ins = $pdo->prepare(
            'INSERT INTO form_submissions (campaign_id, user_id, token, answers_json, created_at)
             VALUES (:c, :u, :t, :j, NOW())'
        );
        $ins->execute([
            'c' => $campaignId,
            'u' => $userId,
            't' => $token,
            'j' => json_encode($answers, JSON_THROW_ON_ERROR),
        ]);

        $templateId = (int) ($row['template_id'] ?? 0);
        $te = new TrackingEventService($pdo);
        $te->record('form_submitted', $userId, $campaignId, $templateId, $token, ['route' => 'feedback']);

        (new RiskScoreService($pdo))->recalculateForUser($userId);

        header('Location: ' . url('/track/thanks/' . rawurlencode($token)) . '?sent=1');
    }

    /**
     * Geri bildirim sonrası teşekkür sayfası (tıklama kaydı oluşturmaz)
     */
    public function thanks(string $token): void
    {
        $raw = rawurldecode($token);
        $pdo = Database::connection();
        $svc = new CampaignTokenService($pdo);
        if ($svc->findTargetByToken($raw) === null) {
            http_response_code(404);
            echo 'Geçersiz bağlantı.';
            return;
        }
        $sent = isset($_GET['sent']);
        $already = isset($_GET['already']);
        require dirname(__DIR__, 2) . '/resources/views/landing/thanks.php';
    }

    /**
     * 1x1 şeffaf GIF — e-posta açılış takibi (güvenilir değildir notu dokümantasyonda)
     */
    public function open(string $token): void
    {
        $pdo = Database::connection();
        $svc = new CampaignTokenService($pdo);
        $target = $svc->findTargetByToken($token);
        if ($target !== null) {
            $te = new TrackingEventService($pdo);
            $stmt = $pdo->prepare('SELECT template_id FROM campaigns WHERE id = :id');
            $stmt->execute(['id' => (int) $target['campaign_id']]);
            $templateId = (int) $stmt->fetchColumn();
            $te->record(
                'email_opened',
                (int) $target['user_id'],
                (int) $target['campaign_id'],
                $templateId,
                $token,
                []
            );
            (new RiskScoreService($pdo))->recalculateForUser((int) $target['user_id']);
        }
        header('Content-Type: image/gif');
        // 1x1 transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    }
}
