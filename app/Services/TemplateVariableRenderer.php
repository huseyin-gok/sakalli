<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Şablon değişkenleri: {{ad_soyad}}, {{benzersiz_link}} vb.
 * XSS: e-posta HTML içinde kullanıcı verisi mutlaka htmlspecialchars ile kaçırılmalı
 */
final class TemplateVariableRenderer
{
    /**
     * @param array<string, string|null> $vars
     */
    public function render(string $content, array $vars, bool $escapeHtml = true): string
    {
        $map = [
            '{{ad_soyad}}' => $vars['ad_soyad'] ?? '',
            '{{ad}}' => $vars['ad'] ?? '',
            '{{soyad}}' => $vars['soyad'] ?? '',
            '{{eposta}}' => $vars['eposta'] ?? '',
            '{{departman}}' => $vars['departman'] ?? '',
            '{{kurum_adi}}' => $vars['kurum_adi'] ?? '',
            '{{benzersiz_link}}' => $vars['benzersiz_link'] ?? '',
            '{{kampanya_adi}}' => $vars['kampanya_adi'] ?? '',
        ];
        $out = str_replace(array_keys($map), array_values($map), $content);
        if ($escapeHtml) {
            // Not: benzersiz_link tam URL ise kaçırma yapılmamalı — ayrı işleyin
            // Burada basit yaklaşım: link placeholder'ı kaçırılmadan bırakılmalı
            return $this->escapeExceptLink($out, $map['{{benzersiz_link}}'] ?? '');
        }
        return $out;
    }

    /**
     * E-posta HTML gövdesi: yer tutucuları doldurur; metin değerleri kaçırılır, link ham URL kalır.
     *
     * @param array<string, string|null> $vars
     */
    public function renderForEmailHtml(string $content, array $vars): string
    {
        $esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $map = [
            '{{ad_soyad}}' => $esc($vars['ad_soyad'] ?? ''),
            '{{ad}}' => $esc($vars['ad'] ?? ''),
            '{{soyad}}' => $esc($vars['soyad'] ?? ''),
            '{{eposta}}' => $esc($vars['eposta'] ?? ''),
            '{{departman}}' => $esc($vars['departman'] ?? ''),
            '{{kurum_adi}}' => $esc($vars['kurum_adi'] ?? ''),
            '{{kampanya_adi}}' => $esc($vars['kampanya_adi'] ?? ''),
            '{{benzersiz_link}}' => (string) ($vars['benzersiz_link'] ?? ''),
        ];

        return str_replace(array_keys($map), array_values($map), $content);
    }

    /**
     * Düz metin ve konu satırı
     *
     * @param array<string, string|null> $vars
     */
    public function renderForEmailPlain(string $content, array $vars): string
    {
        $oneLine = static function (?string $v): string {
            return trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) $v));
        };
        $map = [
            '{{ad_soyad}}' => $oneLine($vars['ad_soyad'] ?? ''),
            '{{ad}}' => $oneLine($vars['ad'] ?? ''),
            '{{soyad}}' => $oneLine($vars['soyad'] ?? ''),
            '{{eposta}}' => $oneLine($vars['eposta'] ?? ''),
            '{{departman}}' => $oneLine($vars['departman'] ?? ''),
            '{{kurum_adi}}' => $oneLine($vars['kurum_adi'] ?? ''),
            '{{kampanya_adi}}' => $oneLine($vars['kampanya_adi'] ?? ''),
            '{{benzersiz_link}}' => $oneLine($vars['benzersiz_link'] ?? ''),
        ];

        return str_replace(array_keys($map), array_values($map), $content);
    }

    private function escapeExceptLink(string $html, string $rawLink): string
    {
        if ($rawLink === '') {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $parts = explode($rawLink, $html, 2);
        if (count($parts) === 1) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return htmlspecialchars($parts[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . $rawLink
            . htmlspecialchars($parts[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
