<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Önizleme ve test postası için örnek değişken değerleri
 */
final class TemplateDemoDataService
{
    /**
     * @return array<string, string>
     */
    public static function variables(?int $userId = null): array
    {
        $kurum = AppBrandingService::kurumAdi();
        $base = rtrim($_ENV['TRACKING_BASE_URL'] ?? '', '/');
        $link = $base !== '' ? $base . '/track/click/ORNEK_TOKEN_ONIZLEME' : 'https://ornek.kurum/track/demo';

        $ad = 'Ayşe';
        $soyad = 'Yılmaz';
        $eposta = 'ayse.yilmaz@ornek.kurum';
        $departman = 'Bilgi İşlem';

        if ($userId !== null) {
            try {
                $pdo = Database::connection();
                $stmt = $pdo->prepare(
                    'SELECT email, display_name, first_name, last_name FROM users WHERE id = :id LIMIT 1'
                );
                $stmt->execute(['id' => $userId]);
                $u = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($u)) {
                    $eposta = (string) ($u['email'] ?? $eposta);
                    if (!empty($u['first_name'])) {
                        $ad = (string) $u['first_name'];
                    }
                    if (!empty($u['last_name'])) {
                        $soyad = (string) $u['last_name'];
                    }
                    if (!empty($u['display_name'])) {
                        $parts = explode(' ', (string) $u['display_name'], 2);
                        $ad = $parts[0] ?: $ad;
                        $soyad = $parts[1] ?? $soyad;
                    }
                }
            } catch (\Throwable) {
                // DB yoksa varsayılanlar
            }
        }

        return [
            'ad_soyad' => trim($ad . ' ' . $soyad),
            'ad' => $ad,
            'soyad' => $soyad,
            'eposta' => $eposta,
            'departman' => $departman,
            'kurum_adi' => $kurum,
            'benzersiz_link' => $link,
            'kampanya_adi' => 'Farkındalık simülasyonu (önizleme)',
        ];
    }
}
