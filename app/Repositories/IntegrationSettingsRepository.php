<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * LDAP / SMTP entegrasyon paketleri — system_settings içinde şifreli metin
 */
final class IntegrationSettingsRepository
{
    public const KEY_LDAP = 'integration_ldap_v1';

    public const KEY_SMTP = 'integration_smtp_v1';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function getBlob(string $key): ?string
    {
        $stmt = $this->db->prepare('SELECT value FROM system_settings WHERE `key` = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' ? $s : null;
    }

    public function saveBlob(string $key, string $encryptedPayload): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO system_settings (`key`, value, is_secret) VALUES (:k, :v, 1)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        $stmt->execute(['k' => $key, 'v' => $encryptedPayload]);
    }

    public function deleteBlob(string $key): void
    {
        $stmt = $this->db->prepare('DELETE FROM system_settings WHERE `key` = :k');
        $stmt->execute(['k' => $key]);
    }
}
