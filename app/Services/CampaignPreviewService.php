<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TemplateRepository;
use PDO;

/**
 * Kampanya gönderim öncesi e-posta ve tıklama akışı önizlemesi (takip kaydı oluşturmaz).
 */
final class CampaignPreviewService
{
    private readonly TemplateVariableRenderer $renderer;

    private readonly TemplateRepository $templates;

    public function __construct(
        private readonly PDO $pdo,
        ?TemplateRepository $templates = null,
    ) {
        $this->templates = $templates ?? new TemplateRepository($pdo);
        $this->renderer = new TemplateVariableRenderer();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function assemble(int $campaignId, ?int $viewerUserId): ?array
    {
        $campaign = $this->loadCampaign($campaignId);
        if ($campaign === null) {
            return null;
        }

        $ver = $this->resolveTemplateVersion($campaign);
        if ($ver === null) {
            return null;
        }

        $flowClickUrl = url('/campaigns/' . $campaignId . '/preview/flow');
        $vars = TemplateDemoDataService::variables($viewerUserId);
        $vars['kampanya_adi'] = (string) ($campaign['name'] ?? '');
        $vars['benzersiz_link'] = $flowClickUrl;

        $subject = $this->renderer->renderForEmailPlain((string) ($ver['subject'] ?? ''), $vars);
        $bodyHtml = $this->renderer->renderForEmailHtml((string) ($ver['body_html'] ?? ''), $vars);

        $base = trim((string) ($campaign['tracking_base_url'] ?? ''));
        if ($base === '') {
            $base = rtrim((string) ($_ENV['TRACKING_BASE_URL'] ?? ''), '/');
        }
        if ($base !== '') {
            $openUrl = $base . '/track/open/PREVIEW_TOKEN';
            $pixel = '<img src="' . htmlspecialchars($openUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" width="1" height="1" alt="" />';
            $bodyHtml = EmailTemplateFooterService::appendHtmlFragment($bodyHtml, $pixel);
        }

        $smtp = IntegrationSettingsService::getSmtpEnv();
        $flow = $this->resolveFlow($campaign);

        return [
            'campaign' => $campaign,
            'email' => [
                'subject' => $subject,
                'from_name' => IntegrationSettingsService::resolveSmtpFromName(
                    isset($campaign['smtp_from_name']) ? (string) $campaign['smtp_from_name'] : null
                ),
                'from_address' => trim((string) ($smtp['SMTP_FROM'] ?? '')),
                'to_example' => (string) ($vars['eposta'] ?? ''),
                'body_html' => $bodyHtml,
                'has_open_pixel' => $base !== '',
            ],
            'flow' => $flow,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadCampaign(int $campaignId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, t.name AS template_name,
                    lp.page_title AS lp_page_title, lp.content_html AS lp_content_html,
                    lp.show_feedback_form AS lp_show_feedback_form,
                    lp.credential_capture AS lp_credential_capture,
                    lp.name AS landing_page_name
             FROM campaigns c
             INNER JOIN templates t ON t.id = c.template_id
             LEFT JOIN landing_pages lp ON lp.id = c.landing_page_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $campaignId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $campaign
     *
     * @return array<string, mixed>|null
     */
    private function resolveTemplateVersion(array $campaign): ?array
    {
        $tvId = (int) ($campaign['template_version_id'] ?? 0);
        if ($tvId > 0) {
            return $this->templates->getVersionById($tvId);
        }
        $tid = (int) ($campaign['template_id'] ?? 0);
        if ($tid <= 0) {
            return null;
        }
        $latestId = $this->templates->getLatestVersionId($tid);

        return $latestId !== null ? $this->templates->getVersionById($latestId) : null;
    }

    /**
     * TrackingController::click ile aynı akış seçimi (kayıt yok).
     *
     * @param array<string, mixed> $campaign
     *
     * @return array<string, mixed>
     */
    private function resolveFlow(array $campaign): array
    {
        $hasLp = !empty($campaign['landing_page_id']);
        $interactionMode = trim((string) ($campaign['interaction_mode'] ?? ''));
        $forceCredentialCapture = $interactionMode === 'credential_capture';
        $forceAwarenessWithForm = $interactionMode === 'awareness_form';
        $forceAwarenessNoForm = $interactionMode === 'awareness_noform';
        $useCredentialCapture = $forceCredentialCapture
            || ($interactionMode === '' && $hasLp && !empty($campaign['lp_credential_capture']));

        if ($useCredentialCapture) {
            $pt = trim((string) ($campaign['lp_page_title'] ?? ''));
            $body = (string) ($campaign['lp_content_html'] ?? '');

            return [
                'type' => 'credential_capture',
                'flow_label' => 'Sahte oturum açma formu',
                'page_title' => $pt !== '' ? $pt : 'Kurumsal oturum açma',
                'body_html' => $body,
                'show_feedback_form' => false,
                'has_thanks_step' => true,
            ];
        }

        $landingRow = null;
        if ($hasLp && trim((string) ($campaign['lp_content_html'] ?? '')) !== '') {
            $landingRow = [
                'page_title' => $campaign['lp_page_title'],
                'content_html' => $campaign['lp_content_html'],
            ];
        }
        $content = AwarenessContentService::resolveForDisplay($this->pdo, $landingRow);

        if ($forceAwarenessNoForm) {
            $showFeedbackForm = false;
            $flowLabel = 'Bilgilendirme (formsuz)';
        } elseif ($forceAwarenessWithForm) {
            $showFeedbackForm = true;
            $flowLabel = 'Bilgilendirme + geri bildirim formu';
        } else {
            $showFeedbackForm = !empty($campaign['lp_show_feedback_form']) && $landingRow !== null;
            $flowLabel = 'Landing şablonu varsayılanı'
                . ($showFeedbackForm ? ' (geri bildirim açık)' : ' (geri bildirim kapalı)');
        }

        return [
            'type' => 'awareness',
            'flow_label' => $flowLabel,
            'page_title' => $content['title'],
            'body_html' => $content['body_html'],
            'show_feedback_form' => $showFeedbackForm,
            'has_thanks_step' => false,
        ];
    }
}
