<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CampaignRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allWithTemplate(): array
    {
        $sql = 'SELECT c.*, t.name AS template_name,
                (SELECT COUNT(*) FROM campaign_targets ct WHERE ct.campaign_id = c.id) AS targets_count
                FROM campaigns c
                INNER JOIN templates t ON t.id = c.template_id
                ORDER BY c.id DESC';

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, t.name AS template_name, lp.name AS landing_page_name, lp.show_feedback_form
             FROM campaigns c
             INNER JOIN templates t ON t.id = c.template_id
             LEFT JOIN landing_pages lp ON lp.id = c.landing_page_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(
        string $name,
        ?string $description,
        int $templateId,
        ?int $templateVersionId,
        ?int $landingPageId,
        string $status,
        ?string $scheduledAt,
        int $batchSize,
        ?int $createdBy,
        ?string $smtpFromName = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO campaigns (name, description, template_id, template_version_id, landing_page_id, status, scheduled_at, send_batch_size, smtp_from_name, created_by)
             VALUES (:n, :d, :tid, :tvid, :lp, :st, :sch, :bs, :sfn, :cb)'
        );
        $stmt->execute([
            'n' => $name,
            'd' => $description,
            'tid' => $templateId,
            'tvid' => $templateVersionId,
            'lp' => $landingPageId !== null && $landingPageId > 0 ? $landingPageId : null,
            'st' => $status,
            'sch' => $scheduledAt ?: null,
            'bs' => $batchSize,
            'sfn' => self::normalizeSmtpFromName($smtpFromName),
            'cb' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateSmtpFromName(int $campaignId, ?string $smtpFromName): void
    {
        $stmt = $this->db->prepare(
            'UPDATE campaigns SET smtp_from_name = :sfn, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id' => $campaignId,
            'sfn' => self::normalizeSmtpFromName($smtpFromName),
        ]);
    }

    private static function normalizeSmtpFromName(?string $smtpFromName): ?string
    {
        $v = trim((string) $smtpFromName);
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, 191);
    }

    public function updateLandingPage(int $campaignId, ?int $landingPageId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE campaigns SET landing_page_id = :lp, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id' => $campaignId,
            'lp' => $landingPageId !== null && $landingPageId > 0 ? $landingPageId : null,
        ]);
    }

    public function updateInteractionMode(int $campaignId, ?string $interactionMode): void
    {
        $stmt = $this->db->prepare(
            'UPDATE campaigns SET interaction_mode = :m, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id' => $campaignId,
            'm' => $interactionMode !== null && trim($interactionMode) !== '' ? trim($interactionMode) : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function targetsForCampaign(int $campaignId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT ct.*, u.email, u.display_name
             FROM campaign_targets ct
             INNER JOIN users u ON u.id = ct.user_id
             WHERE ct.campaign_id = :cid
             ORDER BY ct.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['cid' => $campaignId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countTargets(int $campaignId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM campaign_targets WHERE campaign_id = :c');
        $stmt->execute(['c' => $campaignId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Bilgilendirme sayfası geri bildirimleri (form_submissions)
     *
     * @return list<array<string, mixed>>
     */
    public function formSubmissionsForCampaign(int $campaignId, int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT fs.id, fs.created_at, fs.answers_json, u.email,
                    COALESCE(NULLIF(u.display_name, ''), u.username) AS user_label
             FROM form_submissions fs
             INNER JOIN users u ON u.id = fs.user_id
             WHERE fs.campaign_id = :cid
             ORDER BY fs.id DESC
             LIMIT {$lim}"
        );
        $stmt->execute(['cid' => $campaignId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Her kullanıcı için benzersiz token ile hedef ekler (aynı kullanıcı tekrar eklenmez)
     *
     * @param list<int> $userIds
     * @return int Eklenen satır sayısı
     */
    public function addTargetsBulk(int $campaignId, array $userIds): int
    {
        $bytes = max(16, min(48, (int) ($_ENV['TRACKING_TOKEN_BYTES'] ?? 32)));
        $insert = $this->db->prepare(
            'INSERT IGNORE INTO campaign_targets (campaign_id, user_id, tracking_token, tracking_token_hash, status)
             VALUES (:c, :u, :tok, :th, \'pending\')'
        );
        $added = 0;
        $seen = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0 || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $tok = bin2hex(random_bytes($bytes));
            if (strlen($tok) > 128) {
                $tok = substr($tok, 0, 128);
            }
            $th = hash('sha256', $tok);
            $insert->execute(['c' => $campaignId, 'u' => $uid, 'tok' => $tok, 'th' => $th]);
            $added += $insert->rowCount();
        }

        return $added;
    }

    public function clearTargets(int $campaignId): int
    {
        $stmt = $this->db->prepare('DELETE FROM campaign_targets WHERE campaign_id = :id');
        $stmt->execute(['id' => $campaignId]);

        return $stmt->rowCount();
    }

    public function deleteCampaign(int $campaignId): int
    {
        $stmt = $this->db->prepare('DELETE FROM campaigns WHERE id = :id');
        $stmt->execute(['id' => $campaignId]);

        return $stmt->rowCount();
    }

    /**
     * Tüm hedefleri tekrar gönderim için sıfırlar.
     * - campaign_targets: pending + email_sent_at NULL
     * - email_queue: eski kuyruk satırlarını temizler (yeniden planlama için)
     *
     * @return int Etkilenen hedef sayısı
     */
    public function resetTargetsForResend(int $campaignId): int
    {
        $this->db->beginTransaction();
        try {
            $delQ = $this->db->prepare('DELETE FROM email_queue WHERE campaign_id = :cid');
            $delQ->execute(['cid' => $campaignId]);

            $updT = $this->db->prepare(
                'UPDATE campaign_targets
                 SET status = \'pending\', email_sent_at = NULL, updated_at = NOW()
                 WHERE campaign_id = :cid'
            );
            $updT->execute(['cid' => $campaignId]);
            $n = $updT->rowCount();

            $this->db->commit();

            return $n;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Hedef ekleme / landing değişimi — tüm kampanya durumları (silme sonrası yeniden hedef eklenebilsin)
     */
    public static function canEditTargets(string $status): bool
    {
        return in_array($status, ['draft', 'scheduled', 'sending', 'completed', 'stopped'], true);
    }

    /**
     * Pending hedefleri email_queue’ya alır; kampanya durumu gerekirse tekrar sending yapılır.
     *
     * @return int Eklenen kuyruk satırı sayısı
     */
    public function enqueuePendingTargets(int $campaignId): int
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM campaigns WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $campaignId]);
            if ($lock->fetchColumn() === false) {
                $this->db->rollBack();

                return 0;
            }

            $cntStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM campaign_targets WHERE campaign_id = :cid AND status = \'pending\''
            );
            $cntStmt->execute(['cid' => $campaignId]);
            $n = (int) $cntStmt->fetchColumn();
            if ($n === 0) {
                $this->db->rollBack();

                return 0;
            }

            $ins = $this->db->prepare(
                'INSERT INTO email_queue (campaign_id, campaign_target_id, priority, scheduled_at, status)
                 SELECT c.id, ct.id, 5,
                        GREATEST(NOW(), COALESCE(c.scheduled_at, NOW())),
                        \'pending\'
                 FROM campaign_targets ct
                 INNER JOIN campaigns c ON c.id = ct.campaign_id
                 WHERE ct.campaign_id = :cid AND ct.status = \'pending\''
            );
            $ins->execute(['cid' => $campaignId]);

            $updCt = $this->db->prepare(
                'UPDATE campaign_targets SET status = \'queued\', updated_at = NOW()
                 WHERE campaign_id = :cid AND status = \'pending\''
            );
            $updCt->execute(['cid' => $campaignId]);

            $updC = $this->db->prepare(
                'UPDATE campaigns SET status = \'sending\', updated_at = NOW()
                 WHERE id = :id AND status IN (\'draft\', \'scheduled\', \'sending\', \'completed\', \'stopped\')'
            );
            $updC->execute(['id' => $campaignId]);

            $this->db->commit();

            return $n;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Kuyrukta bekleyen iş kalmadıysa kampanyayı tamamlandı yapar.
     */
    public function tryMarkCampaignCompletedIfQueueDrained(int $campaignId): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM email_queue WHERE campaign_id = :c AND status IN (\'pending\', \'processing\')'
        );
        $stmt->execute(['c' => $campaignId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
        $upd = $this->db->prepare(
            'UPDATE campaigns SET status = \'completed\', updated_at = NOW()
             WHERE id = :id AND status = \'sending\''
        );
        $upd->execute(['id' => $campaignId]);
    }

    /**
     * Yeni pending hedefleri kuyruğa alma (tamamlandı / durduruldu / gönderiliyor sonrası tekrar gönderim için)
     */
    public static function canStartSendQueue(string $status): bool
    {
        return in_array($status, ['draft', 'scheduled', 'sending', 'completed', 'stopped'], true);
    }
}
