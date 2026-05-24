<?php
/** @var string $title */
/** @var list<array<string, mixed>> $events */
require dirname(__DIR__) . '/layouts/main.php';
?>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<p class="text-muted small">Son 200 kayıt. Olay türleri: <code>link_clicked</code>, <code>email_opened</code>, vb.</p>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>Zaman</th>
                <th>Tür</th>
                <th>Kullanıcı</th>
                <th>Kampanya</th>
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
                <tr>
                    <td class="text-nowrap small"><?= e((string) ($ev['created_at'] ?? '')) ?></td>
                    <td><code><?= e((string) ($ev['event_type'] ?? '')) ?></code></td>
                    <td class="small"><?= e((string) ($ev['user_email'] ?? '—')) ?></td>
                    <td class="small"><?= e((string) ($ev['campaign_name'] ?? '—')) ?></td>
                    <td class="small"><?= e((string) ($ev['ip_address'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($events === []): ?>
        <div class="card-body text-muted">Henüz olay yok.</div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
