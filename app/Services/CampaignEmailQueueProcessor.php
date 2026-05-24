<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CampaignRepository;
use App\Repositories\TemplateRepository;
use App\Services\EmailTemplateFooterService;
use PDO;

/**
 * email_queue satırlarını işler: şablon + değişkenler, SMTP, hedef durumu, kampanya tamamlanması
 */
final class CampaignEmailQueueProcessor
{
    private readonly TemplateVariableRenderer $renderer;

    public function __construct(
        private readonly PDO $pdo,
        private readonly SmtpEmailService $smtp,
        private readonly TemplateRepository $templates,
    ) {
        $this->renderer = new TemplateVariableRenderer();
    }

    /**
     * @return int İşlenen (denenen) kuyruk satırı sayısı
     */
    public function processBatch(?int $limit = null, ?int $onlyCampaignId = null): int
    {
        $lim = $limit ?? max(1, min(500, (int) ($_ENV['EMAIL_QUEUE_BATCH_SIZE'] ?? 25)));
        $sql = 'SELECT eq.id AS eq_id, eq.campaign_id, eq.campaign_target_id, eq.attempts,
                       ct.tracking_token,
                       u.id AS user_id, u.email, u.first_name, u.last_name, u.display_name,
                       c.name AS campaign_name, c.template_id, c.template_version_id, c.tracking_base_url, c.smtp_from_name,
                       d.name AS department_name
                FROM email_queue eq
                INNER JOIN campaign_targets ct ON ct.id = eq.campaign_target_id
                INNER JOIN users u ON u.id = ct.user_id
                INNER JOIN campaigns c ON c.id = eq.campaign_id
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE eq.status = :pend AND eq.scheduled_at <= NOW()';
        $params = ['pend' => 'pending'];
        if ($onlyCampaignId !== null && $onlyCampaignId > 0) {
            $sql .= ' AND eq.campaign_id = :cid';
            $params['cid'] = $onlyCampaignId;
        }
        $sql .= ' ORDER BY eq.priority ASC, eq.id ASC LIMIT ' . (int) $lim;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return 0;
        }

        $verCache = [];
        $repo = $this->templates;
        $done = 0;

        foreach ($rows as $job) {
            $eqId = (int) $job['eq_id'];
            $campaignId = (int) $job['campaign_id'];
            $targetId = (int) $job['campaign_target_id'];
            $token = (string) $job['tracking_token'];
            $to = trim((string) $job['email']);
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $this->failJob($eqId, $targetId, 'Geçersiz alıcı e-postası');
                $this->tryCompleteCampaign($campaignId);
                $done++;
                continue;
            }

            $tvId = (int) ($job['template_version_id'] ?? 0);
            if ($tvId <= 0) {
                $tid = (int) $job['template_id'];
                if (!isset($verCache['t' . $tid])) {
                    $verCache['t' . $tid] = $repo->getLatestVersionId($tid);
                }
                $tvId = (int) ($verCache['t' . $tid] ?? 0);
            }
            if (!isset($verCache['v' . $tvId])) {
                $verCache['v' . $tvId] = $tvId > 0 ? $repo->getVersionById($tvId) : null;
            }
            $ver = $verCache['v' . $tvId];
            if (!is_array($ver)) {
                $this->failJob($eqId, $targetId, 'Şablon sürümü bulunamadı');
                $this->tryCompleteCampaign($campaignId);
                $done++;
                continue;
            }

            if (!$this->claimJobForProcessing($eqId)) {
                continue;
            }

            $base = trim((string) ($job['tracking_base_url'] ?? ''));
            if ($base === '') {
                $base = rtrim((string) ($_ENV['TRACKING_BASE_URL'] ?? ''), '/');
            } else {
                $base = rtrim($base, '/');
            }
            $clickUrl = $base !== '' ? $base . '/track/click/' . rawurlencode($token) : '';
            $openUrl = $base !== '' ? $base . '/track/open/' . rawurlencode($token) : '';

            $ad = trim((string) ($job['first_name'] ?? ''));
            $soyad = trim((string) ($job['last_name'] ?? ''));
            $display = trim((string) ($job['display_name'] ?? ''));
            if ($display !== '' && ($ad === '' || $soyad === '')) {
                $parts = preg_split('/\s+/', $display, 2) ?: [];
                if ($ad === '' && isset($parts[0])) {
                    $ad = $parts[0];
                }
                if ($soyad === '' && isset($parts[1])) {
                    $soyad = $parts[1];
                }
            }
            $adSoyad = trim($ad . ' ' . $soyad);
            if ($adSoyad === '' && $display !== '') {
                $adSoyad = $display;
            }

            $vars = [
                'ad_soyad' => $adSoyad !== '' ? $adSoyad : $to,
                'ad' => $ad,
                'soyad' => $soyad,
                'eposta' => $to,
                'departman' => (string) ($job['department_name'] ?? ''),
                'kurum_adi' => AppBrandingService::kurumAdi(),
                'benzersiz_link' => $clickUrl,
                'kampanya_adi' => (string) ($job['campaign_name'] ?? ''),
            ];

            $subject = $this->renderer->renderForEmailPlain((string) ($ver['subject'] ?? ''), $vars);
            $bodyHtml = $this->renderer->renderForEmailHtml((string) ($ver['body_html'] ?? ''), $vars);
            $plainSrc = (string) ($ver['body_plain'] ?? '');
            if ($plainSrc === '') {
                $plainSrc = strip_tags((string) ($ver['body_html'] ?? ''));
            }
            $bodyPlain = $this->renderer->renderForEmailPlain($plainSrc, $vars);

            if ($openUrl !== '') {
                $pixel = '<img src="' . htmlspecialchars($openUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '" width="1" height="1" alt="" />';
                $bodyHtml = EmailTemplateFooterService::appendHtmlFragment($bodyHtml, $pixel);
            }

            $fromName = IntegrationSettingsService::resolveSmtpFromName(
                isset($job['smtp_from_name']) ? (string) $job['smtp_from_name'] : null
            );
            $ok = $this->smtp->send([$to], $subject, $bodyHtml, $bodyPlain, [], $fromName);

            if ($ok) {
                $this->succeedJob($eqId, $targetId, $campaignId, (int) $job['user_id']);
            } else {
                $this->failJob($eqId, $targetId, 'SMTP gönderim hatası');
            }
            $this->tryCompleteCampaign($campaignId);
            $done++;
        }

        return $done;
    }

    /** Başka bir işçi aldıysa false */
    private function claimJobForProcessing(int $eqId): bool
    {
        $u = $this->pdo->prepare(
            'UPDATE email_queue SET status = :st, attempts = attempts + 1, updated_at = NOW() WHERE id = :id AND status = \'pending\''
        );
        $u->execute(['st' => 'processing', 'id' => $eqId]);

        return $u->rowCount() > 0;
    }

    private function succeedJob(int $eqId, int $targetId, int $campaignId, int $userId): void
    {
        $u = $this->pdo->prepare('UPDATE email_queue SET status = :st, updated_at = NOW() WHERE id = :id');
        $u->execute(['st' => 'sent', 'id' => $eqId]);

        $t = $this->pdo->prepare(
            'UPDATE campaign_targets SET status = :st, email_sent_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $t->execute(['st' => 'sent', 'id' => $targetId]);

        if ($userId > 0) {
            $log = $this->pdo->prepare(
                'INSERT INTO email_logs (campaign_id, user_id, message_id, event_type, detail)
                 VALUES (:c, :u, NULL, \'sent\', NULL)'
            );
            $log->execute(['c' => $campaignId, 'u' => $userId]);
        }
    }

    private function failJob(int $eqId, int $targetId, string $err): void
    {
        $err = mb_substr($err, 0, 2000);
        $u = $this->pdo->prepare(
            'UPDATE email_queue SET status = :st, last_error = :e, updated_at = NOW() WHERE id = :id'
        );
        $u->execute(['st' => 'failed', 'e' => $err, 'id' => $eqId]);

        $t = $this->pdo->prepare('UPDATE campaign_targets SET status = :st, updated_at = NOW() WHERE id = :id');
        $t->execute(['st' => 'failed', 'id' => $targetId]);
    }

    private function tryCompleteCampaign(int $campaignId): void
    {
        (new CampaignRepository($this->pdo))->tryMarkCampaignCompletedIfQueueDrained($campaignId);
    }
}
