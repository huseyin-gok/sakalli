<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Olay takibi: tıklama, açılış, form, eğitim vb. — tracking_events tablosuna yazar
 */
final class TrackingEventService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed>|null $metadata Ek bağlam (JSON)
     */
    public function record(
        string $eventType,
        ?int $userId,
        ?int $campaignId,
        ?int $templateId,
        ?string $token,
        ?array $metadata = null
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $ref = $_SERVER['HTTP_REFERER'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO tracking_events
            (user_id, campaign_id, template_id, ip_address, user_agent, referer, token, event_type, metadata, created_at)
            VALUES (:uid, :cid, :tid, :ip, :ua, :ref, :tok, :etype, :meta, NOW())'
        );
        $stmt->execute([
            'uid' => $userId,
            'cid' => $campaignId,
            'tid' => $templateId,
            'ip' => $ip,
            'ua' => $ua,
            'ref' => $ref,
            'tok' => $token,
            'etype' => $eventType,
            'meta' => $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }
}
