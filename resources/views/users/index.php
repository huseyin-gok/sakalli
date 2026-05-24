<?php
/** @var string $title */
/** @var list<array<string, mixed>> $users */
require dirname(__DIR__) . '/layouts/main.php';

$sessionRoles = current_user_role_labels();
$sessionLabel = trim(current_user_display_name());
if ($sessionLabel === '') {
    $sessionLabel = current_user_email();
}
?>
<h1 class="h4 mb-2"><?= e($title) ?></h1>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3 small text-body-secondary">
    <span>Oturum:</span>
    <span class="text-body fw-normal"><?= e($sessionLabel !== '' ? $sessionLabel : '—') ?></span>
    <span class="text-body-secondary">·</span>
    <span>Rolleriniz:</span>
    <?php if ($sessionRoles === []): ?>
        <span class="badge rounded-pill border border-secondary bg-dark text-light fw-normal">Tanımlı rol yok</span>
    <?php else: ?>
        <?php foreach ($sessionRoles as $roleName): ?>
            <span class="badge rounded-pill border border-secondary bg-dark text-light fw-normal"><?= e($roleName) ?></span>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>E-posta</th>
                <th>Ad</th>
                <th>Departman</th>
                <th>Durum</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int) $u['id'] ?></td>
                    <td><?= e((string) $u['email']) ?></td>
                    <td><?= e((string) ($u['display_name'] ?? '')) ?></td>
                    <td><?= e((string) ($u['department'] ?? '')) ?></td>
                    <td><?= !empty($u['is_active']) ? '<span class="badge border border-secondary bg-body-secondary text-body">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
