<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CredentialCaptureRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function insert(
        int $campaignId,
        int $userId,
        ?string $token,
        string $usernameEntered,
        string $passwordEntered,
        ?string $ip,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO credential_captures
            (campaign_id, user_id, token, username_entered, password_entered, ip_address, user_agent, created_at)
             VALUES (:c, :u, :t, :un, :pw, :ip, :ua, NOW())'
        );
        $stmt->execute([
            'c' => $campaignId,
            'u' => $userId,
            't' => $token,
            'un' => $usernameEntered,
            'pw' => $passwordEntered,
            'ip' => $ip,
            'ua' => $userAgent !== null && strlen($userAgent) > 512 ? substr($userAgent, 0, 512) : $userAgent,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCampaign(int $campaignId, int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT cc.*, u.email AS user_email,
                    COALESCE(NULLIF(u.display_name, ''), u.username) AS user_label
             FROM credential_captures cc
             INNER JOIN users u ON u.id = cc.user_id
             WHERE cc.campaign_id = :cid
             ORDER BY cc.id DESC
             LIMIT {$lim}"
        );
        $stmt->execute(['cid' => $campaignId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBetweenDates(string $fromDatetime, string $toDatetime, int $limit = 300): array
    {
        $lim = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT cc.*, u.email,
                    COALESCE(NULLIF(u.display_name, ''), u.username) AS user_label,
                    c.name AS campaign_name, c.id AS campaign_id
             FROM credential_captures cc
             INNER JOIN users u ON u.id = cc.user_id
             INNER JOIN campaigns c ON c.id = cc.campaign_id
             WHERE cc.created_at BETWEEN :f AND :t
             ORDER BY cc.id DESC
             LIMIT {$lim}"
        );
        $stmt->execute(['f' => $fromDatetime, 't' => $toDatetime]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
