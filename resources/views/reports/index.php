<?php
/** @var string $title */
/** @var list<array<string, mixed>> $by_department */
/** @var list<array<string, mixed>> $risk_by_level */
/** @var list<array<string, mixed>> $form_submissions */
/** @var list<array<string, mixed>> $credential_captures */
/** @var string $from */
/** @var string $to */
$risk_by_level = $risk_by_level ?? [];
$form_submissions = $form_submissions ?? [];
$credential_captures = $credential_captures ?? [];
$levelLabels = [
    'low' => 'Düşük',
    'medium' => 'Orta',
    'high' => 'Yüksek',
    'critical' => 'Kritik',
];
require dirname(__DIR__) . '/layouts/main.php';
?>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<form class="row g-2 mb-3" method="get" action="<?= url('/reports') ?>">
    <div class="col-auto">
        <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filtrele</button>
    </div>
    <div class="col-auto">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/export') . '?from=' . rawurlencode($from) . '&to=' . rawurlencode($to)) ?>">CSV indir</a>
    </div>
</form>
<div class="card shadow-sm mb-4">
    <div class="card-header">Bilgilendirme geri bildirimleri (seçili tarih aralığı, en fazla 300)</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
            <tr>
                <th>Tarih</th>
                <th>Kampanya</th>
                <th>Kullanıcı</th>
                <th>Anladım</th>
                <th>Mesaj</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($form_submissions === []): ?>
                <tr><td colspan="5" class="text-muted small">Bu aralıkta geri bildirim yok.</td></tr>
            <?php else: ?>
                <?php foreach ($form_submissions as $fs): ?>
                    <?php
                    $raw = (string) ($fs['answers_json'] ?? '{}');
                    $ans = json_decode($raw, true);
                    $comment = is_array($ans) ? trim((string) ($ans['comment'] ?? '')) : '';
                    $und = is_array($ans) ? (string) ($ans['understood'] ?? '') : '';
                    $cid = (int) ($fs['campaign_id'] ?? 0);
                    ?>
                    <tr>
                        <td class="small text-nowrap"><?= e((string) ($fs['created_at'] ?? '')) ?></td>
                        <td class="small">
                            <?php if ($cid > 0): ?>
                                <a href="<?= url('/campaigns/' . $cid) ?>"><?= e((string) ($fs['campaign_name'] ?? '')) ?></a>
                            <?php else: ?>
                                <?= e((string) ($fs['campaign_name'] ?? '')) ?>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e((string) ($fs['email'] ?? '')) ?></td>
                        <td><?= e(feedback_understood_label($und)) ?></td>
                        <td class="small"><?= $comment !== '' ? nl2br(e($comment)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card shadow-sm mb-4 border-warning border-opacity-50">
    <div class="card-header bg-warning bg-opacity-10">Kimlik yakalama simülasyonu (tarih aralığı, en fazla 300)</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
            <tr>
                <th>Tarih</th>
                <th>Kampanya</th>
                <th>Hedef kullanıcı</th>
                <th>Girilen kullanıcı adı</th>
                <th>Girilen parola</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($credential_captures === []): ?>
                <tr><td colspan="5" class="text-muted small">Bu aralıkta kayıt yok.</td></tr>
            <?php else: ?>
                <?php foreach ($credential_captures as $cc): ?>
                    <?php $ccid = (int) ($cc['campaign_id'] ?? 0); ?>
                    <tr>
                        <td class="small text-nowrap"><?= e((string) ($cc['created_at'] ?? '')) ?></td>
                        <td class="small">
                            <?php if ($ccid > 0): ?>
                                <a href="<?= url('/campaigns/' . $ccid) ?>"><?= e((string) ($cc['campaign_name'] ?? '')) ?></a>
                            <?php else: ?>
                                <?= e((string) ($cc['campaign_name'] ?? '')) ?>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e((string) ($cc['email'] ?? '')) ?></td>
                        <td class="small font-monospace"><?= e((string) ($cc['username_entered'] ?? '')) ?></td>
                        <td class="small font-monospace text-break"><?= e((string) ($cc['password_entered'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card shadow-sm mb-4">
    <div class="card-header">Risk skoru özeti (güncel)</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Seviye</th><th>Kullanıcı</th><th>Ortalama skor</th></tr></thead>
            <tbody>
            <?php if ($risk_by_level === []): ?>
                <tr><td colspan="3" class="text-muted small">Henüz risk kaydı yok (olay oluştukça güncellenir).</td></tr>
            <?php else: ?>
                <?php foreach ($risk_by_level as $r): ?>
                    <tr>
                        <td><?= e($levelLabels[(string) ($r['level'] ?? '')] ?? (string) ($r['level'] ?? '')) ?></td>
                        <td><?= (int) ($r['users_count'] ?? 0) ?></td>
                        <td><?= e((string) ($r['avg_score'] ?? '0')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-header">Departman bazlı olay sayısı</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Departman</th><th>Olay</th></tr></thead>
            <tbody>
            <?php foreach ($by_department as $row): ?>
                <tr>
                    <td><?= e((string) ($row['department'] ?? '—')) ?></td>
                    <td><?= (int) ($row['events'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
