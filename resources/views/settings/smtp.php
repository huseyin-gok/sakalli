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
$enc = strtolower((string) ($v['SMTP_ENCRYPTION'] ?? 'tls'));
?>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<?php if ($saved): ?>
    <div class="alert alert-success py-2">SMTP ayarları şifrelenerek veritabanına kaydedildi.</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2"><?= e((string) $error) ?></div>
<?php endif; ?>
<?php if (!$encryption_ready): ?>
    <div class="alert alert-warning py-2">
        Kayıt için şifreleme anahtarı gerekli. İsterseniz <code>.env</code> ile anahtar verin; yoksa <code>storage/secrets/</code> yazılabilir olmalı — uygulama <code>app_encryption.key</code> dosyasını otomatik oluşturur.
    </div>
<?php else: ?>
    <p class="text-muted small mb-2">Şifreleme anahtarı kaynağı: <code><?= e($encryption_source ?? '') ?></code></p>
<?php endif; ?>
<p class="text-muted small">
    <?php if ($from_db): ?>
        <span class="badge text-bg-secondary">Aktif kaynak: veritabanı</span> Kuyruk gönderimi ve şablon testi bu değerleri kullanır.
    <?php else: ?>
        <span class="badge text-bg-secondary">Aktif kaynak: .env</span> İlk kayıttan sonra SMTP DB’de şifreli tutulur.
    <?php endif; ?>
</p>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/settings') ?>">Genel ayarlar</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/settings/ldap') ?>">LDAP</a>
</div>
<form method="post" action="<?= url('/settings/smtp') ?>" class="row g-3" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="col-md-8">
        <label class="form-label" for="smtp_host">SMTP_HOST</label>
        <input type="text" name="smtp_host" id="smtp_host" class="form-control" value="<?= e($v['SMTP_HOST'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="smtp_port">SMTP_PORT</label>
        <input type="number" name="smtp_port" id="smtp_port" class="form-control" value="<?= e($v['SMTP_PORT'] ?? '587') ?>" min="1" max="65535">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="smtp_encryption">SMTP_ENCRYPTION</label>
        <select name="smtp_encryption" id="smtp_encryption" class="form-select">
            <option value="tls"<?= $enc === 'tls' ? ' selected' : '' ?>>tls</option>
            <option value="ssl"<?= $enc === 'ssl' ? ' selected' : '' ?>>ssl</option>
            <option value="none"<?= $enc === 'none' ? ' selected' : '' ?>>none</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="smtp_user">SMTP_USER</label>
        <input type="text" name="smtp_user" id="smtp_user" class="form-control" value="<?= e($v['SMTP_USER'] ?? '') ?>" autocomplete="username">
    </div>
    <div class="col-12">
        <label class="form-label" for="smtp_password">SMTP_PASSWORD</label>
        <input type="password" name="smtp_password" id="smtp_password" class="form-control" autocomplete="new-password"
               placeholder="Boş bırakırsanız mevcut şifre korunur">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="smtp_from">SMTP_FROM</label>
        <input type="email" name="smtp_from" id="smtp_from" class="form-control" value="<?= e($v['SMTP_FROM'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="smtp_from_name">SMTP_FROM_NAME</label>
        <input type="text" name="smtp_from_name" id="smtp_from_name" class="form-control" value="<?= e($v['SMTP_FROM_NAME'] ?? '') ?>">
    </div>
    <div class="col-12 mt-2">
        <button type="submit" class="btn btn-primary"<?= $encryption_ready ? '' : ' disabled' ?>>Kaydet (şifreli)</button>
    </div>
</form>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
