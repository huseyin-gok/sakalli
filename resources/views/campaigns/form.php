<?php
/** @var string $title */
/** @var list<array<string, mixed>> $templates */
/** @var list<array{id: int, name: string}> $landing_pages */
/** @var string $csrf */
/** @var string|null $error */
/** @var string $default_smtp_from_name */
$landing_pages = $landing_pages ?? [];
$default_smtp_from_name = $default_smtp_from_name ?? '';
require dirname(__DIR__) . '/layouts/main.php';
?>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($templates === []): ?>
    <div class="alert alert-warning">Önce <a href="<?= url('/templates/create') ?>">şablon oluşturun</a>.</div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url('/campaigns/create') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="mb-3">
                <label class="form-label">Kampanya adı</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Şablon</label>
                <select name="template_id" class="form-select" required>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="smtp_from_name">E-postada görünen gönderen adı</label>
                <input type="text" name="smtp_from_name" id="smtp_from_name" class="form-control" maxlength="191"
                    placeholder="<?= $default_smtp_from_name !== '' ? e($default_smtp_from_name) : 'Örn. Microsoft 365 Güvenlik' ?>">
                <div class="form-text">
                    Alıcının posta istemcisinde görünen isim (<code>From</code> display name). Boş bırakılırsa SMTP varsayılanı kullanılır
                    <?php if ($default_smtp_from_name !== ''): ?>
                        (<strong><?= e($default_smtp_from_name) ?></strong>).
                    <?php else: ?>
                        (Ayarlar → SMTP).
                    <?php endif; ?>
                    E-posta adresi yine <code>SMTP_FROM</code> kalır. Agresif taklit için şablondaki marka adına uygun isim kullanın (ör. <em>Ziraat Bankası Kredi Merkezi</em>, <em>Microsoft Hesap Ekibi</em>).
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Takip linki bilgilendirme sayfası</label>
                <select name="landing_page_id" class="form-select">
                    <option value="0">Varsayılan (Ayarlar’daki genel metin)</option>
                    <?php foreach ($landing_pages as $lp): ?>
                        <option value="<?= (int) $lp['id'] ?>"><?= e((string) $lp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    Özel metin için <a href="<?= url('/settings/landing-pages') ?>" target="_blank" rel="noopener">Ayarlar → Bilgilendirme şablonları</a>.
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Durum</label>
                    <select name="status" class="form-select">
                        <option value="draft">Taslak</option>
                        <option value="scheduled">Planlandı</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Planlanan gönderim</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Batch boyutu</label>
                    <input type="number" name="send_batch_size" class="form-control" value="50" min="1" max="500">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <a class="btn btn-outline-secondary" href="<?= url('/campaigns') ?>">İptal</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
