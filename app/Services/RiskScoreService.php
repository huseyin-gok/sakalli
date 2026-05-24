<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Risk skoru hesaplama — örnek ağırlıklar (iş kurallarına göre ayarlanır)
 */
final class RiskScoreService
{
    public function __construct(private readonly PDO $db)
    {
    }

    private const WEIGHT_OPEN = 2.0;
    private const WEIGHT_CLICK = 15.0;
    private const WEIGHT_FORM = 35.0;
    private const WEIGHT_CREDENTIALS = 45.0;

    /**
     * Kullanıcı için skoru yeniden hesapla ve risk_scores tablosuna yaz
     */
    public function recalculateForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'SELECT event_type, COUNT(*) AS c FROM tracking_events WHERE user_id = :u GROUP BY event_type'
        );
        $stmt->execute(['u' => $userId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[(string) $r['event_type']] = (int) $r['c'];
        }

        $score = 0.0;
        $breakdown = [];

        $add = function (string $type, float $w, string $label) use (&$rows, &$score, &$breakdown): void {
            $c = (int) ($rows[$type] ?? 0);
            if ($c > 0) {
                $delta = $w * $c;
                $score += $delta;
                $breakdown[$label] = round($delta, 2);
            }
        };

        // Örnek olay tipleri — tracking_events.event_type ile uyumlu sabitler kullanın
        $add('email_opened', self::WEIGHT_OPEN, 'eposta_acildi');
        $add('link_clicked', self::WEIGHT_CLICK, 'link_tiklandi');
        $add('form_submitted', self::WEIGHT_FORM, 'form_gonderildi');
        $add('credentials_submitted', self::WEIGHT_CREDENTIALS, 'kimlik_formu_gonderildi');

        $score = max(0.0, min(100.0, $score));
        $level = $this->toLevel($score);

        $up = $this->db->prepare(
            'INSERT INTO risk_scores (user_id, score, level, breakdown_json, updated_at)
             VALUES (:u, :s, :l, :b, NOW())
             ON DUPLICATE KEY UPDATE score = VALUES(score), level = VALUES(level), breakdown_json = VALUES(breakdown_json), updated_at = NOW()'
        );
        $up->execute([
            'u' => $userId,
            's' => $score,
            'l' => $level,
            'b' => json_encode($breakdown, JSON_THROW_ON_ERROR),
        ]);
    }

    private function toLevel(float $score): string
    {
        if ($score < 20) {
            return 'low';
        }
        if ($score < 45) {
            return 'medium';
        }
        if ($score < 70) {
            return 'high';
        }
        return 'critical';
    }
}
