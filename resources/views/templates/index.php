<?php
/** @var string $title */
/** @var list<array<string, mixed>> $templates */
/** @var array<string, string> $categories */
/** @var string $csrf */
require dirname(__DIR__) . '/layouts/main.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a class="btn btn-primary btn-sm" href="<?= url('/templates/create') ?>">Yeni şablon</a>
</div>
<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-success py-2">Kayıt güncellendi.</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success py-2">Şablon silindi.</div>
<?php endif; ?>
<?php if (!empty($_GET['err'])): ?>
    <div class="alert alert-danger py-2">
        <?php
        $err = (string) ($_GET['err'] ?? '');
        echo match ($err) {
            'in_use' => 'Şablon silinemedi: ' . (int) ($_GET['usage'] ?? 0) . ' kampanyada kullanılıyor.',
            'not_found' => 'Şablon bulunamadı.',
            default => 'Şablon silinirken hata oluştu.',
        };
        ?>
    </div>
<?php endif; ?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Ad</th>
                <th>Kategori</th>
                <th>Son konu</th>
                <th>Durum</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
                <tr>
                    <td><?= (int) $t['id'] ?></td>
                    <td><?= e((string) $t['name']) ?></td>
                    <td><?= e($categories[(string) $t['category']] ?? $t['category']) ?></td>
                    <td class="small text-muted"><?= e((string) ($t['latest_subject'] ?? '—')) ?></td>
                    <td><?= !empty($t['is_active']) ? '<span class="badge border border-secondary bg-body-secondary text-body">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>' ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/templates/' . (int) $t['id'] . '/preview') ?>" target="_blank" rel="noopener" title="Önizle">Önizle</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/templates/edit/' . (int) $t['id']) ?>">Düzenle</a>
                        <form method="post" action="<?= url('/templates/delete/' . (int) $t['id']) ?>" class="d-inline" onsubmit="return confirm('Bu şablonu silmek istiyor musunuz?');">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($templates === []): ?>
        <div class="card-body text-muted">Henüz şablon yok.</div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
