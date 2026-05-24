<?php
/** @var string $title */
/** @var list<array<string, mixed>> $pages */
/** @var string $csrf */
/** @var bool $deleted */
/** @var bool $saved */
require dirname(__DIR__, 2) . '/layouts/main.php';
?>
<div class="mb-3">
    <a class="small" href="<?= url('/settings') ?>">← Ayarlar</a>
</div>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<?php if (!empty($saved)): ?>
    <div class="alert alert-success py-2">Kaydedildi.</div>
<?php endif; ?>
<?php if (!empty($deleted)): ?>
    <div class="alert alert-warning py-2">Şablon silindi. Bu şablonu kullanan kampanyalarda alan sıfırlandı (varsayılan bilgilendirme kullanılır).</div>
<?php endif; ?>
<p class="text-muted small mb-3">
    Her kampanya için <strong>Ayarlar → şablon listesinden</strong> bir bilgilendirme sayfası seçebilirsiniz; seçilmezse
    <a href="<?= url('/settings') ?>">Ayarlar</a> sayfasındaki genel varsayılan metin kullanılır.
</p>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span></span>
    <a class="btn btn-primary btn-sm" href="<?= url('/settings/landing-pages/create') ?>">Yeni şablon</a>
</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
            <tr>
                <th>Ad</th>
                <th>Slug</th>
                <th>Sayfa başlığı</th>
                <th>Mod</th>
                <th class="text-end">İşlem</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($pages === []): ?>
                <tr>
                    <td colspan="5" class="text-muted">Henüz şablon yok.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($pages as $p): ?>
                    <tr>
                        <td><?= e((string) ($p['name'] ?? '')) ?></td>
                        <td><code><?= e((string) ($p['slug'] ?? '')) ?></code></td>
                        <td class="small"><?= e((string) ($p['page_title'] ?? '—')) ?></td>
                        <td class="small">
                            <?php if (!empty($p['credential_capture'])): ?>
                                <span class="badge bg-warning text-dark">Kimlik</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Bilgi</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-outline-secondary btn-sm" href="<?= url('/settings/landing-pages/edit/' . (int) $p['id']) ?>">Düzenle</a>
                            <form method="post" action="<?= url('/settings/landing-pages/delete/' . (int) $p['id']) ?>" class="d-inline"
                                  onsubmit="return confirm('Bu şablon silinsin mi?');">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
