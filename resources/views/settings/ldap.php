<?php
/** @var string $title */
/** @var string $csrf */
/** @var array<string, string> $v */
/** @var bool $saved */
/** @var string|null $error */
/** @var bool $from_db */
/** @var bool $encryption_ready */
/** @var string $encryption_source */
require dirname(__DIR__) . '/layouts/main.php';
$cb = static function (string $k): bool {
    return filter_var($k, FILTER_VALIDATE_BOOLEAN);
};
$info = static function (string $text): string {
    return '<span class="badge rounded-pill text-bg-secondary ms-1 align-middle" '
        . 'title="' . e($text) . '" data-bs-toggle="tooltip" data-bs-placement="top" '
        . 'style="font-size:.68rem; line-height:1; cursor:help;">i</span>';
};
?>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<?php if ($saved): ?>
    <div class="alert alert-success py-2">LDAP ayarları şifrelenerek veritabanına kaydedildi.</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2"><?= e((string) $error) ?></div>
<?php endif; ?>
<?php if (!$encryption_ready): ?>
    <div class="alert alert-warning py-2">
        Kayıt için şifreleme anahtarı gerekli. <code>.env</code> içinde <code>SETTINGS_ENCRYPTION_KEY</code> veya <code>APP_SECRET</code> tanımlayabilirsiniz;
        tanımlamazsanız uygulama <code>storage/secrets/app_encryption.key</code> dosyasını otomatik oluşturur — bu klasörün web sunucusu kullanıcısı tarafından yazılabilir olduğundan emin olun.
    </div>
<?php else: ?>
    <p class="text-muted small mb-2">Şifreleme anahtarı kaynağı: <code><?= e($encryption_source ?? '') ?></code></p>
<?php endif; ?>
<p class="text-muted small">
    <?php if ($from_db): ?>
        <span class="badge text-bg-secondary">Aktif kaynak: veritabanı</span> Aşağıdaki değerler şifreli paketten okunur; .env yalnızca eksik alanlarda yedek olarak kullanılır.
    <?php else: ?>
        <span class="badge text-bg-secondary">Aktif kaynak: .env</span> İlk kayıttan sonra ayarlar DB’de şifreli tutulur.
    <?php endif; ?>
</p>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/settings') ?>">Genel ayarlar</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/settings/smtp') ?>">SMTP</a>
</div>
<form method="post" action="<?= url('/settings/ldap') ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="col-12"><h2 class="h6 text-secondary border-bottom pb-2">Sunucu</h2></div>
    <div class="col-md-8">
        <label class="form-label" for="ldap_host">LDAP_HOST <?= $info('LDAP sunucusunun adresi. Örn: ldap://dc01.firma.local') ?></label>
        <input type="text" name="ldap_host" id="ldap_host" class="form-control" value="<?= e($v['LDAP_HOST'] ?? '') ?>"
               placeholder="ldap://dc01.example.local">
        <div class="form-text">Alan adı / IP ve protokol burada tanımlanır.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="ldap_port">LDAP_PORT <?= $info('LDAP portu. 389 (LDAP/STARTTLS), 636 (LDAPS)') ?></label>
        <input type="number" name="ldap_port" id="ldap_port" class="form-control" value="<?= e($v['LDAP_PORT'] ?? '389') ?>" min="1" max="65535">
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ldap_use_tls" id="ldap_use_tls" value="1"
                <?= $cb($v['LDAP_USE_TLS'] ?? '') ? ' checked' : '' ?>>
            <label class="form-check-label" for="ldap_use_tls">LDAP_USE_TLS (STARTTLS) <?= $info('389 portunda STARTTLS açar. 636/ldaps kullanıyorsanız genelde kapalı olur.') ?></label>
        </div>
    </div>

    <div class="col-12 mt-2"><h2 class="h6 text-secondary border-bottom pb-2">Servis hesabı</h2></div>
    <div class="col-12">
        <label class="form-label" for="ldap_bind_dn">LDAP_BIND_DN <?= $info('LDAP arama yapacak servis hesabının DN değeri.') ?></label>
        <input type="text" name="ldap_bind_dn" id="ldap_bind_dn" class="form-control" value="<?= e($v['LDAP_BIND_DN'] ?? '') ?>" autocomplete="off">
        <div class="form-text">Örn: CN=servis,OU=IT,DC=firma,DC=local</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="ldap_bind_password">LDAP_BIND_PASSWORD <?= $info('Servis hesabı şifresi. Boş bırakırsanız kayıtlı şifre korunur.') ?></label>
        <input type="password" name="ldap_bind_password" id="ldap_bind_password" class="form-control" autocomplete="new-password"
               placeholder="Boş bırakırsanız mevcut şifre korunur">
    </div>

    <div class="col-12 mt-2"><h2 class="h6 text-secondary border-bottom pb-2">Dizin ve filtreler</h2></div>
    <div class="col-12">
        <label class="form-label" for="ldap_base_dn">LDAP_BASE_DN <?= $info('Kullanıcı aramalarının başlayacağı kök DN. Yanlış olursa kullanıcı bulunamaz.') ?></label>
        <input type="text" name="ldap_base_dn" id="ldap_base_dn" class="form-control" value="<?= e($v['LDAP_BASE_DN'] ?? '') ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="ldap_user_filter">LDAP_USER_FILTER <?= $info('Kullanıcı adı ile arama filtresi. %s yerine girişte yazılan kullanıcı adı koyulur.') ?></label>
        <input type="text" name="ldap_user_filter" id="ldap_user_filter" class="form-control font-monospace small" value="<?= e($v['LDAP_USER_FILTER'] ?? '') ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="ldap_user_filter_upn">LDAP_USER_FILTER_UPN <?= $info('E-posta/UPN ile girişte kullanılacak LDAP filtresi. %s zorunlu yer tutucudur.') ?></label>
        <input type="text" name="ldap_user_filter_upn" id="ldap_user_filter_upn" class="form-control font-monospace small" value="<?= e($v['LDAP_USER_FILTER_UPN'] ?? '') ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="ldap_panel_allowed_dn_substring">LDAP_PANEL_ALLOWED_DN_SUBSTRING <?= $info('Parantez () içindeki OU/dn parçasına sahip kullanıcılar panele girebilir. Birden fazla OU için ortak parça kullanın veya boş bırakın.') ?></label>
        <input type="text" name="ldap_panel_allowed_dn_substring" id="ldap_panel_allowed_dn_substring" class="form-control" value="<?= e($v['LDAP_PANEL_ALLOWED_DN_SUBSTRING'] ?? '') ?>">
        <div class="form-text">Boşsa OU kısıtı uygulanmaz. Örn: <code>OU=ITDept</code> yazarsanız DN içinde bu parçayı taşıyan kullanıcılar panele girebilir.</div>
    </div>

    <div class="col-12 mt-2"><h2 class="h6 text-secondary border-bottom pb-2">İlk girişte yerel kullanıcı</h2></div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ldap_auto_provision" id="ldap_auto_provision" value="1"
                <?= $cb($v['LDAP_AUTO_PROVISION'] ?? '') ? ' checked' : '' ?>>
            <label class="form-check-label" for="ldap_auto_provision">LDAP_AUTO_PROVISION <?= $info('LDAP’de doğrulanan ama yerelde kaydı olmayan kullanıcıyı otomatik users tablosuna ekler.') ?></label>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="ldap_auto_provision_role">LDAP_AUTO_PROVISION_ROLE <?= $info('Otomatik açılan kullanıcıya atanacak rol slug değeri.') ?></label>
        <input type="text" name="ldap_auto_provision_role" id="ldap_auto_provision_role" class="form-control" value="<?= e($v['LDAP_AUTO_PROVISION_ROLE'] ?? '') ?>">
    </div>

    <div class="col-12 mt-2"><h2 class="h6 text-secondary border-bottom pb-2">Kampanya — OU hedefi</h2></div>
    <div class="col-md-6">
        <label class="form-label" for="ldap_ou_search_base">LDAP_OU_SEARCH_BASE <?= $info('Kampanya ekranında OU listesi bu taban altında aranır.') ?></label>
        <input type="text" name="ldap_ou_search_base" id="ldap_ou_search_base" class="form-control" value="<?= e($v['LDAP_OU_SEARCH_BASE'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="ldap_ou_name_filter">LDAP_OU_NAME_FILTER <?= $info('Sadece adı bu metni içeren OU’lar listelensin.') ?></label>
        <input type="text" name="ldap_ou_name_filter" id="ldap_ou_name_filter" class="form-control" value="<?= e($v['LDAP_OU_NAME_FILTER'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="ldap_ou_target_role">LDAP_OU_TARGET_ROLE <?= $info('OU’dan gelen yeni kullanıcıya kampanya hedefi eklenirken atanacak varsayılan rol.') ?></label>
        <input type="text" name="ldap_ou_target_role" id="ldap_ou_target_role" class="form-control" value="<?= e($v['LDAP_OU_TARGET_ROLE'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="ldap_ou_target_email_domain">LDAP_OU_TARGET_EMAIL_DOMAIN <?= $info('Mail/UPN boş kullanıcılar için e-posta üretirken kullanılacak domain.') ?></label>
        <input type="text" name="ldap_ou_target_email_domain" id="ldap_ou_target_email_domain" class="form-control" value="<?= e($v['LDAP_OU_TARGET_EMAIL_DOMAIN'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="ldap_ou_fetch_limit">LDAP_OU_FETCH_LIMIT <?= $info('Bir OU taramasında okunacak en fazla kullanıcı sayısı.') ?></label>
        <input type="number" name="ldap_ou_fetch_limit" id="ldap_ou_fetch_limit" class="form-control" value="<?= e($v['LDAP_OU_FETCH_LIMIT'] ?? '10000') ?>" min="1" max="100000">
    </div>

    <div class="col-12 mt-3">
        <button type="submit" class="btn btn-primary"<?= $encryption_ready ? '' : ' disabled' ?>>Kaydet (şifreli)</button>
    </div>
</form>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    if (window.bootstrap && window.bootstrap.Tooltip) {
        new window.bootstrap.Tooltip(el);
    }
});
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
