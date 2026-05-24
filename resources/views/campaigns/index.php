<?php
/** @var string $title */
/** @var list<array<string, mixed>> $campaigns */
/** @var array<string, string> $status_labels */
/** @var string $csrf */
require dirname(__DIR__) . '/layouts/main.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a class="btn btn-primary btn-sm" href="<?= url('/campaigns/create') ?>">Yeni kampanya</a>
</div>
<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-success py-2">Kampanya kaydedildi.</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success py-2">Kampanya silindi.</div>
<?php endif; ?>
<?php if (!empty($_GET['err'])): ?>
    <div class="alert alert-danger py-2">
        <?php
        $err = (string) ($_GET['err'] ?? '');
        echo match ($err) {
            'not_found' => 'Kampanya bulunamadı.',
            default => 'Kampanya silinirken hata oluştu.',
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
                <th>Şablon</th>
                <th>Durum</th>
                <th>Hedef</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td><?= (int) $c['id'] ?></td>
                    <td><?= e((string) $c['name']) ?></td>
                    <td><?= e((string) $c['template_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($status_labels[(string) $c['status']] ?? $c['status']) ?></span></td>
                    <td><?= (int) ($c['targets_count'] ?? 0) ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/campaigns/' . (int) $c['id']) ?>">Detay</a>
                        <form method="post" action="<?= url('/campaigns/delete/' . (int) $c['id']) ?>" class="d-inline" onsubmit="return confirm('Bu kampanyayı silmek istiyor musunuz?');">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($campaigns === []): ?>
        <div class="card-body text-muted">Henüz kampanya yok.</div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
