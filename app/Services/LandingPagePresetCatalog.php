<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Takip linki bilgilendirme sayfası için hazır HTML şablonları (panel editöründe).
 *
 * @phpstan-type Preset array{id: string, name: string, page_title: string, html: string, suggested_name?: string, suggested_slug?: string, credential_capture?: bool}
 */
final class LandingPagePresetCatalog
{
    /**
     * @return list<Preset>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'classic',
                'name' => 'Klasik — simülasyon ve ipuçları',
                'page_title' => AwarenessContentService::defaultTitle(),
                'html' => AwarenessContentService::defaultBodyHtml(),
                'suggested_name' => 'Klasik bilgilendirme',
                'suggested_slug' => 'klasik-bilgilendirme',
            ],
            [
                'id' => 'short',
                'name' => 'Kısa — tek ekran özeti',
                'page_title' => 'Bilgi güvenliği — Test tamamlandı',
                'html' => <<<'HTML'
<div class="alert alert-info border-0 shadow-sm">
    <strong>Bu bir testti.</strong> Tıkladığınız bağlantı, kurumunuzun yürüttüğü farkındalık simülasyonunun parçasıydı.
</div>
<p class="mb-2">Parola veya kişisel veri toplanmadı. Amaç güvenli davranışları güçlendirmektir.</p>
<p class="mb-0 small text-muted">Şüpheli e-postaları kurumunuzun bildirdiği kanala iletin.</p>
HTML,
                'suggested_name' => 'Kısa bilgilendirme',
                'suggested_slug' => 'kisa-bilgilendirme',
            ],
            [
                'id' => 'corporate',
                'name' => 'Kurumsal — resmi ton',
                'page_title' => 'Kurumsal bilgi güvenliği bildirimi',
                'html' => <<<'HTML'
<p class="text-uppercase small text-muted mb-2">Bilgi güvenliği birimi</p>
<h1 class="h4">Farkındalık testi hakkında</h1>
<p>
    Az önce etkileşimde bulunduğunuz bağlantı, <strong>onaylı bir güvenlik farkındalık programı</strong> kapsamında
    yönetilen kontrollü bir senaryoydu. Kimlik doğrulama veya veri toplama amacı taşımamaktadır.
</p>
<p>
    Bu tür testler, olası sosyal mühendislik risklerini değerlendirmek ve güvenli iletişim pratiklerini desteklemek için düzenlenmektedir.
</p>
<hr class="my-4">
<p class="small mb-0">
    Resmi prosedürler ve iletişim kanalları için lütfen kurum içi bilgi güvenliği yönergelerine başvurunuz.
</p>
HTML,
                'suggested_name' => 'Kurumsal bilgilendirme',
                'suggested_slug' => 'kurumsal-bilgilendirme',
            ],
            [
                'id' => 'training_focus',
                'name' => 'Eğitim odaklı — madde madde',
                'page_title' => 'Güvenli e-posta — Hatırlatma',
                'html' => <<<'HTML'
<h1 class="h4 mb-3">Tebrikler, öğrenme fırsatı</h1>
<p class="lead">Bu tıklama bir <strong>öğrenme anı</strong>dır — gerçek bir saldırı değil.</p>
<div class="card bg-light border-0 mb-3">
    <div class="card-body">
        <h2 class="h6 text-dark">Bugünkü ders</h2>
        <ul class="mb-0">
            <li>Önce <strong>göndereni</strong> doğrulayın (adres ile isim uyuşuyor mu?).</li>
            <li><strong>Aciliyet</strong> ve baskı ifadelerine karşı duraklayın.</li>
            <li>Beklenmeyen <strong>ek ve linklerde</strong> risk yüksektir.</li>
            <li>Şüphe halinde <strong>BT / güvenlik</strong> hattına danışın.</li>
        </ul>
    </div>
</div>
<p class="small text-muted mb-0">Bu sayfa eğitim amaçlıdır; kişisel veri toplamaz.</p>
HTML,
                'suggested_name' => 'Eğitim odaklı sayfa',
                'suggested_slug' => 'egitim-bilgilendirme',
            ],
            [
                'id' => 'friendly',
                'name' => 'Samimi — yumuşak dil',
                'page_title' => 'Merak ettiniz — bu bir güvenlik alıştırmasıydı',
                'html' => <<<'HTML'
<div class="text-center mb-4">
    <span class="badge rounded-pill text-bg-dark px-3 py-2">Her şey yolunda</span>
</div>
<h1 class="h4 text-center mb-3">Endişelenmenize gerek yok</h1>
<p>
    Tıkladığınız link, kurumunuzun sizi <strong>zorda bırakmak için değil</strong>, güvenlik alışkanlıklarınızı
    nazikçe gözden geçirmek için hazırlanmış bir simülasyondu.
</p>
<p>
    Kimse şifrenizi istemedi; sadece “acaba bu linke tıklar mıydım?” sorusuna birlikte cevap aradık.
</p>
<p class="mb-0">
    Bir dahaki sefere şüphelenirseniz, içgüdünüze güvenin ve kurallara göre bildirin — bu tam olarak istediğimiz davranış.
</p>
HTML,
                'suggested_name' => 'Samimi bilgilendirme',
                'suggested_slug' => 'samimi-bilgilendirme',
            ],
            [
                'id' => 'fake_login_corporate',
                'name' => 'Kimlik yakalama — sahte kurumsal giriş (kullanıcı adı + parola)',
                'page_title' => 'Kurumsal hesap — Oturum açın',
                'credential_capture' => true,
                'html' => <<<'HTML'
<p class="text-center mb-3"><span class="badge bg-secondary">Şifreli bağlantı</span></p>
<h1 class="h5 text-center mb-3">Kurumsal oturum açma</h1>
<p class="small text-muted text-center mb-0">
    Kurum uygulamalarına devam etmek için kurumsal hesabınızla oturum açın. Bu adım bilgi güvenliği gereği zorunludur.
</p>
HTML,
                'suggested_name' => 'Sahte kurum girişi',
                'suggested_slug' => 'sahte-kurum-giris',
            ],
            [
                'id' => 'fake_microsoft_login',
                'name' => 'Kimlik yakalama — Microsoft 365 oturum',
                'page_title' => 'Oturum açın — Microsoft',
                'credential_capture' => true,
                'html' => <<<'HTML'
<div class="text-center mb-4">
    <span style="font-size:28px;font-weight:600;color:#0078d4;font-family:'Segoe UI',Arial,sans-serif;">Microsoft</span>
</div>
<p class="small text-center text-muted mb-3">Kurumsal hesabınızla oturum açın</p>
<p class="small text-center text-muted mb-0">Güvenli oturum açma hizmeti · Oturumunuz kurumunuz tarafından yönetilir</p>
HTML,
                'suggested_name' => 'Microsoft — Sahte oturum',
                'suggested_slug' => 'microsoft-oturum',
            ],
            [
                'id' => 'fake_ziraat_login',
                'name' => 'Kimlik yakalama — Ziraat İnternet Şubesi',
                'page_title' => 'Ziraat Bankası İnternet Şubesi',
                'credential_capture' => true,
                'html' => <<<'HTML'
<div class="text-center mb-3">
    <span style="display:inline-block;padding:10px 20px;background:#c8102e;color:#fff;font-family:Arial,sans-serif;font-size:18px;font-weight:bold;border-radius:2px;">Ziraat Bankası</span>
</div>
<p class="text-center small text-muted mb-2">İnternet Şubesi · Güvenli giriş</p>
<p class="text-center small mb-0">Müşteri numaranız ve şifreniz ile giriş yapın. Bu oturum 256-bit SSL ile korunmaktadır.</p>
HTML,
                'suggested_name' => 'Ziraat — Sahte internet şubesi',
                'suggested_slug' => 'ziraat-internet-subesi',
            ],
            [
                'id' => 'fake_gib_form',
                'name' => 'Kimlik yakalama — GİB e-Bildirim doğrulama',
                'page_title' => 'Gelir İdaresi Başkanlığı — e-Bildirim',
                'credential_capture' => true,
                'html' => <<<'HTML'
<div class="mb-3" style="background:#37474f;color:#fff;padding:12px 16px;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">Gelir İdaresi Başkanlığı</div>
<p class="small mb-2">Vergi iadesi ödemesinin yapılabilmesi için e-Devlet / GİB kimlik doğrulaması gerekmektedir.</p>
<p class="small text-muted mb-0">T.C. kimlik numaranız ve şifreniz ile devam edin. Bildirim No: GIB-2026-44821</p>
HTML,
                'suggested_name' => 'GİB — Sahte doğrulama',
                'suggested_slug' => 'gib-dogrulama',
            ],
            [
                'id' => 'fake_edevlet_login',
                'name' => 'Kimlik yakalama — e-Devlet giriş',
                'page_title' => 'e-Devlet Kapısı — Giriş',
                'credential_capture' => true,
                'html' => <<<'HTML'
<div class="text-center mb-3">
    <span style="display:inline-block;padding:12px 24px;background:#1565c0;color:#fff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">e-Devlet Kapısı</span>
</div>
<p class="small text-center text-muted mb-2">Başvurunuza devam etmek için giriş yapın</p>
<p class="small text-center mb-0">T.C. Kimlik No ve e-Devlet şifresi · Güvenli bağlantı</p>
HTML,
                'suggested_name' => 'e-Devlet — Sahte giriş',
                'suggested_slug' => 'edevlet-giris',
            ],
        ];
    }

    public static function toJson(): string
    {
        return json_encode(
            self::all(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE
        );
    }
}
