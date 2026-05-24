<?php

/** @var string $title */
/** @var list<array<string, mixed>> $settings */
/** @var string $csrf */
/** @var bool $saved */
/** @var bool $awareness_saved */
/** @var array{title: string, body_html: string} $awareness */
/** @var string $awareness_default_title */
/** @var string $awareness_default_body */
/** @var string $landing_presets_json */
/** @var bool $branding_saved */
/** @var string $branding_err */
/** @var string $branding_logo_path */
/** @var string $branding_logo_effective */
$branding_logo_effective = $branding_logo_effective ?? 'images/sakalli-logo.png';
$landing_presets_json = $landing_presets_json ?? '[]';
require dirname(__DIR__) . '/layouts/main.php';
?>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<?php if ($saved): ?>
    <div class="alert alert-success py-2">Kaydedildi.</div>
<?php endif; ?>
<?php if (!empty($awareness_saved)): ?>
    <div class="alert alert-success py-2">Bilgilendirme sayfası güncellendi.</div>
<?php endif; ?>
<?php if (!empty($branding_saved)): ?>
    <div class="alert alert-success py-2">Logo ayarı kaydedildi.</div>
<?php endif; ?>
<?php if (($branding_err ?? '') === 'upload'): ?>
    <div class="alert alert-danger py-2">Dosya yüklenemedi (boyut sınırı veya sunucu hatası). <code>upload_max_filesize</code> / <code>post_max_size</code> değerlerine bakın.</div>
<?php elseif (($branding_err ?? '') === 'invalid'): ?>
    <div class="alert alert-danger py-2">Geçersiz dosya: yalnızca JPEG, PNG, GIF veya WebP (en fazla 2 MB) kabul edilir.</div>
<?php endif; ?>
<div class="alert alert-info small">
    <strong>LDAP</strong> ve <strong>SMTP</strong> sol menüden ayrı sayfalarda; değerler veritabanında şifreli saklanabilir (öncelik DB, yoksa <code>.env</code>).
    Şifreleme anahtarı: isteğe bağlı <code>SETTINGS_ENCRYPTION_KEY</code> / <code>APP_SECRET</code>; yoksa <code>storage/secrets/app_encryption.key</code> otomatik üretilir (klasör yazılabilir olmalı).
    <strong><code>kurum_adi</code></strong> anahtarı, e-posta şablonlarındaki <code>{{kurum_adi}}</code> değişkenine yazılır (yoksa yedek: SMTP gönderen adı).
    Logo aşağıdaki <strong>Görünüm / logo</strong> kartından yönetilir; veritabanında <code>branding_logo_path</code> anahtarı tutulur.
</div>
<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/settings/ldap') ?>">LDAP ayarları</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/settings/smtp') ?>">SMTP ayarları</a>
</div>
<div class="card shadow-sm mb-4">
    <div class="card-header">Görünüm / logo</div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Panel, giriş, bilgilendirme sayfaları ve e-posta alt bilgisi aynı logoyu kullanır.
            Dosyayı <code>public/images/</code> altına koymanız yeterli (ör. <code>sakalli-logo.png</code> veya <code>logo.png</code>).
            Ayarlarda yol boşsa veya dosya yoksa <strong>public/images</strong> içindeki logo otomatik bulunur.
            Şu an kullanılan: <code><?= e($branding_logo_effective) ?></code>
            <strong>Gözat</strong> ile yükleme yaparsanız <code>uploads/branding/</code> kullanılır; yalnızca <code>public/images</code> logosunu istiyorsanız logo yolunu boş bırakın.
        </p>
        <form method="post" action="<?= url('/settings/save-branding') ?>" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="col-12">
                <label class="form-label" for="branding_logo">Dosyadan logo yükle</label>
                <input type="file" name="branding_logo" id="branding_logo" class="form-control"
                    accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp">
                <div class="form-text">JPEG, PNG, GIF veya WebP; en fazla 2 MB. Dosya <code>public/uploads/branding/</code> altına kaydedilir.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="branding_logo_path">Logo yolu veya URL (isteğe bağlı)</label>
                <input type="text" name="branding_logo_path" id="branding_logo_path" class="form-control"
                    value="<?= e($branding_logo_path) ?>"
                    placeholder="<?= e($branding_logo_effective) ?> veya boş bırakın (public/images)"
                    maxlength="512" autocomplete="off">
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-primary w-100">Logoyu kaydet</button>
            </div>
            <div class="col-12">
                <div class="text-muted small mb-1">Önizleme</div>
                <img src="<?= e(branding_logo_url()) ?>" alt="Logo önizleme" width="106" height="106"
                    class="rounded-3 object-fit-contain d-block" style="max-height:96px;width:auto;" loading="lazy"
                    onerror="this.style.display='none'">
            </div>
        </form>
    </div>
</div>
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Bilgilendirme şablonları (kampanya başına)</span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/settings/landing-pages') ?>">Şablonları yönet</a>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-0">
            Birden fazla HTML bilgilendirme sayfası tanımlayıp her <strong>kampanyada</strong> hangisinin kullanılacağını seçebilirsiniz.
            Hiçbiri seçilmezse aşağıdaki <strong>genel varsayılan</strong> metin uygulanır.
        </p>
    </div>
</div>
<div class="card shadow-sm mb-4">
    <div class="card-header">Genel varsayılan bilgilendirme (kampanyada şablon yoksa)</div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Takip bağlantısına tıklanınca açılan sayfa için yedek metin. Kampanyaya özel şablon atanmadığında kullanılır.
            Başlık veya gövde boş bırakılırsa uygulamanın gömülü varsayılanı kullanılır. HTML ve Bootstrap 5 sınıfları kullanılabilir.
        </p>
        <form method="post" action="<?= url('/settings/save-awareness') ?>" class="row g-3" id="awareness-settings-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="col-12">
                <label class="form-label" for="awareness_title">Sayfa başlığı (<code>&lt;title&gt;</code>)</label>
                <input type="text" name="awareness_title" id="awareness_title" class="form-control"
                    value="<?= e($awareness['title']) ?>"
                    placeholder="<?= e($awareness_default_title) ?>">
            </div>
            <div class="col-12">
                <?php require dirname(__DIR__) . '/partials/tinymce_landing_toolbar.php'; ?>
                <label class="form-label" for="awareness_body_html">Sayfa gövdesi (HTML, token satırının üstü) <span class="text-muted fw-normal">(TinyMCE)</span></label>
                <textarea name="awareness_body_html" id="awareness_body_html" class="form-control" rows="14"><?= e($awareness['body_html']) ?></textarea>
                <div class="form-text">Boş bırakırsanız varsayılan metin kullanılır. Hazır şablon veya aşağıdaki ham HTML’e bakın.</div>
                <?php
                $textarea_id = 'awareness_body_html';
                $form_id = 'awareness-settings-form';
                $title_input_id = 'awareness_title';
                $name_input_id = '';
                $slug_input_id = '';
                require dirname(__DIR__) . '/partials/tinymce_landing_scripts.php';
                ?>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Bilgilendirme sayfasını kaydet</button>
            </div>
        </form>
        <details class="mt-3">
            <summary class="text-muted small">Varsayılan gövde HTML (kopyalamak için)</summary>
            <pre class="small bg-body-secondary p-2 rounded mt-2 mb-0 overflow-auto" style="max-height:14rem;"><?= e($awareness_default_body) ?></pre>
        </details>
    </div>
</div>
<div class="card shadow-sm mb-4">
    <div class="card-header">Kayıtlı ayarlar</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Anahtar</th>
                    <th>Değer</th>
                    <th>Gizli</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($settings as $s): ?>
                    <tr>
                        <td><code><?= e((string) ($s['key'] ?? '')) ?></code></td>
                        <td><?= !empty($s['is_secret']) ? '••••' : e((string) ($s['value'] ?? '')) ?></td>
                        <td><?= !empty($s['is_secret']) ? 'Evet' : 'Hayır' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($settings === []): ?>
        <div class="card-body text-muted">Kayıt yok. Örnek: <code>kurum_adi</code>, <code>timezone</code></div>
    <?php endif; ?>
</div>
<div class="card shadow-sm">
    <div class="card-header">Yeni / güncelle</div>
    <div class="card-body">
        <form method="post" action="<?= url('/settings/save') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="col-md-4">
                <label class="form-label">Anahtar</label>
                <input type="text" name="new_key" class="form-control" placeholder="kurum_adi" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Değer</label>
                <input type="text" name="new_value" class="form-control" placeholder="Kurum A.Ş.">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Kaydet</button>
            </div>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>