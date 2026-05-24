<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Kampanya hedefi için benzersiz takip token üretimi ve doğrulama
 * Token ham değeri e-postada kullanılır; DB'de hash saklanabilir (opsiyonel katman)
 */
final class CampaignTokenService
{
    public function __construct(
        private readonly PDO $db,
        private readonly int $tokenBytes = 32
    ) {
    }

    /**
     * Kriptografik olarak güçlü rastgele token (hex)
     */
    public function generateRawToken(): string
    {
        return bin2hex(random_bytes(max(16, $this->tokenBytes)));
    }

    /**
     * campaign_targets kaydına token atar (INSERT sonrası veya UPDATE)
     */
    public function assignTokenToTarget(int $campaignTargetId, string $rawToken): void
    {
        $hash = hash('sha256', $rawToken);
        $stmt = $this->db->prepare(
            'UPDATE campaign_targets SET tracking_token = :token, tracking_token_hash = :th, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'token' => $rawToken, // İsterseniz sadece hash saklayıp e-postada token'ı ayrı tabloda tutun
            'th' => $hash,
            'id' => $campaignTargetId,
        ]);
    }

    /**
     * Ham token ile hedef kaydını bul (tıklama / landing)
     *
     * @return array<string, mixed>|null
     */
    public function findTargetByToken(string $rawToken): ?array
    {
        // Güvenlik: önce hash ile eşleştirme tercih edilir
        $hash = hash('sha256', $rawToken);
        $stmt = $this->db->prepare(
            'SELECT * FROM campaign_targets WHERE tracking_token_hash = :h OR tracking_token = :t LIMIT 1'
        );
        $stmt->execute(['h' => $hash, 't' => $rawToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
