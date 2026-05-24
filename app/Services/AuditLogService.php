<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Denetim günlüğü — kritik işlemler ve oturum olayları
 */
final class AuditLogService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function log(
        ?int $actorUserId,
        string $action,
        string $entityType,
        ?string $entityId,
        ?array $payload = null,
        ?string $ip = null
    ): void {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip_address, payload, created_at)
             VALUES (:a, :act, :et, :eid, :ip, :p, NOW())'
        );
        $stmt->execute([
            'a' => $actorUserId,
            'act' => $action,
            'et' => $entityType,
            'eid' => $entityId,
            'ip' => $ip,
            'p' => $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        ]);
    }
}
