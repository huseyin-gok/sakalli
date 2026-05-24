<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Tüm kampanya e-postalarının HTML gövdesi altına logo + bilgilendirme metni ekler.
 */
final class EmailTemplateFooterService
{
    private const FOOTER_LINE1 = 'Unutmayın: Her sakallıyı deden zannetmediğiniz gibi, her linki de doğru zannetmeyin.';

    private const FOOTER_LINE2 = 'Herhangi bir şüpheli durumda lütfen Bilgi İşlem Müdürlüğü ile iletişime geçiniz.';

    public static function footerMottoLine(): string
    {
        return self::FOOTER_LINE1;
    }

    public static function footerContactLine(): string
    {
        return self::FOOTER_LINE2;
    }

    /**
     * Takip sonrası bilgilendirme sayfasında iletişim hatırlatması (HTML).
     */
    public static function awarenessCalloutHtml(): string
    {
        $l1 = htmlspecialchars(self::FOOTER_LINE1, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $l2 = htmlspecialchars(self::FOOTER_LINE2, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div class="alert alert-secondary border mt-4" role="note">'
            . '<p class="small fw-semibold mb-2">İletişim</p>'
            . '<p class="small mb-0">' . $l2 . '</p>'
            . '</div>';
    }

    /**
     * Logo ve statik metin için mutlak taban URL (…/public); boşsa img atlanır.
     */
    public static function resolvePublicBaseUrl(?string $trackingOrCampaignBase): string
    {
        $t = $trackingOrCampaignBase !== null ? rtrim(trim($trackingOrCampaignBase), '/') : '';
        if ($t !== '') {
            return $t;
        }
        $app = rtrim(trim((string) ($_ENV['APP_URL'] ?? '')), '/');
        if ($app !== '') {
            return $app;
        }

        return rtrim(trim((string) ($_ENV['TRACKING_BASE_URL'] ?? '')), '/');
    }

    /**
     * Tam HTML şablonlarda parçayı </body> veya </html> kapanışından önce ekler (Outlook / OWA uyumu).
     */
    public static function appendHtmlFragment(string $html, string $fragment): string
    {
        $lower = strtolower($html);
        $pos = strripos($lower, '</body>');
        if ($pos !== false) {
            return substr($html, 0, $pos) . $fragment . substr($html, $pos);
        }
        $pos = strripos($lower, '</html>');
        if ($pos !== false) {
            return substr($html, 0, $pos) . $fragment . substr($html, $pos);
        }

        return $html . $fragment;
    }

    public static function appendFooterHtml(string $html, string $publicBaseUrl): string
    {
        return self::appendHtmlFragment($html, self::buildFooterTableHtml($publicBaseUrl));
    }

    private static function buildFooterTableHtml(string $publicBaseUrl): string
    {
        $base = rtrim($publicBaseUrl, '/');
        $logoUrl = AppBrandingService::logoHrefForEmail($base);
        $l1 = htmlspecialchars(self::FOOTER_LINE1, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $l2 = htmlspecialchars(self::FOOTER_LINE2, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logoBlock = '';
        if ($logoUrl !== '') {
            $src = htmlspecialchars($logoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $logoBlock = '<tr><td align="center" style="padding:0 12px 12px 12px;">'
                . '<img src="' . $src . '" alt="Sakallı" width="120" '
                . 'style="display:block;margin:0 auto;max-width:120px;width:120px;height:auto;border:0;outline:none;text-decoration:none;" />'
                . '</td></tr>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="margin-top:24px;border-top:1px solid #dddddd;padding-top:16px;" data-sakalli-footer="1">'
            . $logoBlock
            . '<tr><td align="center" style="font-family:Segoe UI, Arial, Helvetica, sans-serif;font-size:12px;line-height:1.55;color:#555555;padding:0 12px 8px 12px;">'
            . $l1
            . '</td></tr>'
            . '<tr><td align="center" style="font-family:Segoe UI, Arial, Helvetica, sans-serif;font-size:12px;line-height:1.55;color:#555555;padding:0 12px 0 12px;">'
            . $l2
            . '</td></tr>'
            . '</table>';
    }

    public static function appendFooterPlain(string $plain): string
    {
        $sep = "\n\n---\n";

        return rtrim($plain) . $sep . self::FOOTER_LINE1 . "\n\n" . self::FOOTER_LINE2 . "\n";
    }
}
