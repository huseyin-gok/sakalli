<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use PDO;

/**
 * Yönetim paneli özet metrikleri ve son aktiviteler
 */
final class DashboardController
{
    /** @var array<string, string> */
    private const CAMPAIGN_STATUS_LABELS = [
        'draft' => 'Taslak',
        'scheduled' => 'Planlandı',
        'sending' => 'Gönderiliyor',
        'completed' => 'Tamamlandı',
        'stopped' => 'Durduruldu',
    ];

    public function index(): void
    {
        require_auth();

        $pdo = Database::connection();
        $base = $this->aggregateMetrics($pdo);

        $showSecurity = \rbac_has_any_role(['super_admin', 'security_manager']);
        $showReports = \rbac_has_any_role(['super_admin', 'security_manager', 'report_viewer']);

        $extra = [];
        $recentCampaigns = [];
        $recentEvents = [];
        $recentForms = [];
        $recentCredentials = [];

        if ($showSecurity) {
            $extra = array_merge($extra, $this->securityMetrics($pdo));
            $recentCampaigns = $this->fetchRecentCampaigns($pdo);
            $recentForms = $this->fetchRecentForms($pdo);
            $recentCredentials = $this->fetchRecentCredentials($pdo);
        }
        if ($showReports) {
            $extra = array_merge($extra, $this->reportMetrics($pdo));
            $recentEvents = $this->fetchRecentEvents($pdo);
        }

        View::render('dashboard/index', [
            'title' => 'Dashboard',
            'metrics' => array_merge($base, $extra),
            'recent_campaigns' => $recentCampaigns,
            'recent_events' => $recentEvents,
            'recent_forms' => $recentForms,
            'recent_credentials' => $recentCredentials,
            'show_security_block' => $showSecurity,
            'show_reports_block' => $showReports,
            'campaign_status_labels' => self::CAMPAIGN_STATUS_LABELS,
        ]);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function aggregateMetrics(PDO $pdo): array
    {
        $sent = (int) $pdo->query(
            "SELECT COUNT(*) FROM email_logs WHERE event_type IN ('sent','delivered')"
        )->fetchColumn();

        $clicked = (int) $pdo->query(
            "SELECT COUNT(DISTINCT CONCAT(IFNULL(user_id,0),'-',IFNULL(campaign_id,0)))
             FROM tracking_events WHERE event_type = 'link_clicked'"
        )->fetchColumn();

        $forms = (int) $pdo->query(
            'SELECT COUNT(*) FROM form_submissions'
        )->fetchColumn();

        $sentPairs = (int) $pdo->query(
            "SELECT COUNT(DISTINCT CONCAT(campaign_id,'-',user_id)) FROM email_logs
             WHERE event_type IN ('sent','delivered')"
        )->fetchColumn();
        $den = max(1, $sentPairs);
        $clickRate = round(100.0 * $clicked / $den, 2);

        return [
            'emails_sent' => $sent,
            'click_rate' => $clickRate,
            'forms' => $forms,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function securityMetrics(PDO $pdo): array
    {
        $campaignsTotal = (int) $pdo->query('SELECT COUNT(*) FROM campaigns')->fetchColumn();
        $campaignsSending = (int) $pdo->query(
            "SELECT COUNT(*) FROM campaigns WHERE status = 'sending'"
        )->fetchColumn();
        $queuePending = (int) $pdo->query(
            "SELECT COUNT(*) FROM email_queue WHERE status IN ('pending','processing')"
        )->fetchColumn();
        $usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
        $templatesCount = (int) $pdo->query('SELECT COUNT(*) FROM templates')->fetchColumn();
        $landingCount = (int) $pdo->query('SELECT COUNT(*) FROM landing_pages')->fetchColumn();
        $credentialTotal = (int) $pdo->query('SELECT COUNT(*) FROM credential_captures')->fetchColumn();

        return [
            'campaigns_total' => $campaignsTotal,
            'campaigns_sending' => $campaignsSending,
            'email_queue_pending' => $queuePending,
            'users_active' => $usersCount,
            'templates_total' => $templatesCount,
            'landing_pages_total' => $landingCount,
            'credential_captures_total' => $credentialTotal,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function reportMetrics(PDO $pdo): array
    {
        $riskElevated = (int) $pdo->query(
            "SELECT COUNT(*) FROM risk_scores WHERE level IN ('high','critical')"
        )->fetchColumn();

        $tracking7d = (int) $pdo->query(
            "SELECT COUNT(*) FROM tracking_events WHERE created_at >= (NOW() - INTERVAL 7 DAY)"
        )->fetchColumn();

        return [
            'risk_elevated_users' => $riskElevated,
            'tracking_events_7d' => $tracking7d,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecentCampaigns(PDO $pdo, int $limit = 6): array
    {
        $lim = max(1, min(20, $limit));
        $sql = 'SELECT c.id, c.name, c.status, c.updated_at,
                (SELECT COUNT(*) FROM campaign_targets ct WHERE ct.campaign_id = c.id) AS targets_count
                FROM campaigns c
                ORDER BY COALESCE(c.updated_at, c.created_at) DESC, c.id DESC
                LIMIT ' . $lim;

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecentEvents(PDO $pdo, int $limit = 12): array
    {
        $lim = max(1, min(30, $limit));
        $stmt = $pdo->query(
            'SELECT te.id, te.event_type, te.created_at, u.email AS user_email, c.name AS campaign_name
             FROM tracking_events te
             LEFT JOIN users u ON u.id = te.user_id
             LEFT JOIN campaigns c ON c.id = te.campaign_id
             ORDER BY te.created_at DESC, te.id DESC
             LIMIT ' . $lim
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['event_label'] = $this->eventTypeLabel((string) ($row['event_type'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecentForms(PDO $pdo, int $limit = 6): array
    {
        $lim = max(1, min(20, $limit));
        $stmt = $pdo->query(
            'SELECT fs.id, fs.created_at, u.email AS user_email, c.name AS campaign_name
             FROM form_submissions fs
             INNER JOIN users u ON u.id = fs.user_id
             INNER JOIN campaigns c ON c.id = fs.campaign_id
             ORDER BY fs.created_at DESC, fs.id DESC
             LIMIT ' . $lim
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecentCredentials(PDO $pdo, int $limit = 6): array
    {
        $lim = max(1, min(20, $limit));
        $stmt = $pdo->query(
            'SELECT cc.id, cc.created_at, u.email AS user_email, c.name AS campaign_name, cc.username_entered
             FROM credential_captures cc
             INNER JOIN users u ON u.id = cc.user_id
             INNER JOIN campaigns c ON c.id = cc.campaign_id
             ORDER BY cc.created_at DESC, cc.id DESC
             LIMIT ' . $lim
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function eventTypeLabel(string $type): string
    {
        return match ($type) {
            'link_clicked' => 'Bağlantı tıklandı',
            'email_opened' => 'E-posta açıldı',
            'form_submitted' => 'Geri bildirim gönderildi',
            'credentials_submitted' => 'Kimlik formu gönderildi',
            'training_completed' => 'Eğitim tamamlandı',
            default => $type !== '' ? $type : '—',
        };
    }
}
