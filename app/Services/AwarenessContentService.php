<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Takip tıklaması sonrası bilgilendirme sayfası — içerik system_settings’ten veya varsayılandan.
 */
final class AwarenessContentService
{
    public const SETTING_TITLE = 'awareness_page_title';

    public const SETTING_BODY_HTML = 'awareness_body_html';

    private const DEFAULT_TITLE = 'Güvenlik Farkındalığı — Simülasyon';

    private const DEFAULT_BODY_HTML = <<<'HTML'
<span class="badge text-bg-secondary mb-2">Kurum içi farkındalık simülasyonu</span>
<h1 class="h3 mb-3">Bu bir phishing saldırısı değildi</h1>
<p class="lead">
    Az önce tıkladığınız bağlantı, <strong>bilgi güvenliği farkındalık testi</strong> kapsamında
    kurumunuz tarafından yönetilen bir simülasyonun parçasıydı.
</p>
<p>
    Gerçek parolanız doğrulanmadı; kimlik bilgileriniz toplanmadı. Amaç, olası riskleri ölçmek ve
    güvenli davranışları pekiştirmektir.
</p>
<h2 class="h5 mt-4">Şüpheli e-postalarda nelere dikkat etmelisiniz?</h2>
<ul>
    <li>Gönderen adresi ile görünen isim uyumsuz olabilir.</li>
    <li>Aciliyet ve tehdit içeren dil sık kullanılır.</li>
    <li>Beklenmeyen ekler ve kısa bağlantılar risk taşır.</li>
    <li>Kurumsal iletilerde genelde resmi imza ve iletişim bilgisi bulunur.</li>
</ul>
<h2 class="h5 mt-4">Şüpheli e-postayı nasıl bildirirsiniz?</h2>
<p>
    Kurumunuzun tanımladığı kanala (ör. BT güvenlik hattı veya “şüpheli e-posta bildir” düğmesi)
    iletmelisiniz. Prosedürü İK veya BT politikalarından kontrol edin.
</p>
HTML;

    /**
     * Panel formu: veritabanındaki ham değerler (boş = varsayılan kullanılır).
     *
     * @return array{title: string, body_html: string}
     */
    public static function getRawForForm(PDO $pdo): array
    {
        $stmt = $pdo->prepare(
            'SELECT `key`, value FROM system_settings WHERE `key` IN (:k1, :k2)'
        );
        $stmt->execute([
            'k1' => self::SETTING_TITLE,
            'k2' => self::SETTING_BODY_HTML,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'title' => trim((string) ($rows[self::SETTING_TITLE] ?? '')),
            'body_html' => (string) ($rows[self::SETTING_BODY_HTML] ?? ''),
        ];
    }

    /**
     * Ziyaretçi sayfası: boş alanlar varsayılan metinle doldurulur.
     *
     * @param array<string, mixed>|null $landingRow landing_pages satırı (kampanyaya atanmış şablon)
     * @return array{title: string, body_html: string}
     */
    public static function resolveForDisplay(PDO $pdo, ?array $landingRow = null): array
    {
        if ($landingRow !== null) {
            $body = (string) ($landingRow['content_html'] ?? '');
            $title = trim((string) ($landingRow['page_title'] ?? ''));

            return [
                'title' => $title !== '' ? $title : self::DEFAULT_TITLE,
                'body_html' => $body !== '' ? $body : self::defaultBodyHtml(),
            ];
        }

        $raw = self::getRawForForm($pdo);

        return [
            'title' => $raw['title'] !== '' ? $raw['title'] : self::DEFAULT_TITLE,
            'body_html' => trim($raw['body_html']) !== '' ? $raw['body_html'] : self::defaultBodyHtml(),
        ];
    }

    public static function defaultTitle(): string
    {
        return self::DEFAULT_TITLE;
    }

    public static function defaultBodyHtml(): string
    {
        return self::DEFAULT_BODY_HTML;
    }
}
