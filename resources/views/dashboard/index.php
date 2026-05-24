<?php
/** @var string $title */
/** @var array<string, int|float|string> $metrics */
/** @var list<array<string, mixed>> $recent_campaigns */
/** @var list<array<string, mixed>> $recent_events */
/** @var list<array<string, mixed>> $recent_forms */
/** @var list<array<string, mixed>> $recent_credentials */
/** @var bool $show_security_block */
/** @var bool $show_reports_block */
/** @var array<string, string> $campaign_status_labels */
require dirname(__DIR__) . '/layouts/main.php';

$dashFmt = static function (?string $s): string {
    if ($s === null || $s === '') {
        return '—';
    }
    $t = strtotime($s);

    return $t !== false ? date('d.m.Y H:i', $t) : '—';
};
$greet = trim(current_user_display_name());
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-body-secondary small mb-0">
            <?php if ($greet !== ''): ?>
                Hoş geldiniz, <span class="text-body"><?= e($greet) ?></span>. Özet metrikler ve son aktiviteler aşağıda.
            <?php else: ?>
                Özet metrikler ve son aktiviteler.
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-secondary h-100 shadow-sm">
            <div class="card-body">
                <div class="text-body-secondary small">Gönderilen e-posta (log)</div>
                <div class="fs-3 fw-semibold"><?= (int) ($metrics['emails_sent'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-secondary h-100 shadow-sm">
            <div class="card-body">
                <div class="text-body-secondary small">Tıklama oranı (yaklaşık)</div>
                <div class="fs-3 fw-semibold"><?= e((string) ($metrics['click_rate'] ?? 0)) ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-secondary h-100 shadow-sm">
            <div class="card-body">
                <div class="text-body-secondary small">Geri bildirim (form)</div>
                <div class="fs-3 fw-semibold"><?= (int) ($metrics['forms'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($show_security_block): ?>
    <h2 class="h6 text-body-secondary text-uppercase mt-4 mb-3" style="letter-spacing: .06em;">Güvenlik &amp; kampanya</h2>
    <div class="row g-3 mb-2">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">Kampanya</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['campaigns_total'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">Gönderimde</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['campaigns_sending'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">E-posta kuyruğu</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['email_queue_pending'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">Aktif kullanıcı</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['users_active'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">Şablon</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['templates_total'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">Landing sayfası</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['landing_pages_total'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-secondary h-100 shadow-sm border-warning border-opacity-50">
                <div class="card-body">
                    <div class="text-body-secondary small">Kimlik simülasyonu kaydı</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['credential_captures_total'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($show_reports_block): ?>
    <h2 class="h6 text-body-secondary text-uppercase mt-4 mb-3" style="letter-spacing: .06em;">Olay &amp; risk</h2>
    <div class="row g-3 mb-2">
        <div class="col-6 col-md-4">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">Yüksek / kritik risk (kullanıcı)</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['risk_elevated_users'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-secondary h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-body-secondary small">Takip olayı (son 7 gün)</div>
                    <div class="fs-4 fw-semibold"><?= (int) ($metrics['tracking_events_7d'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 my-4">
    <?php if ($show_security_block): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/campaigns') ?>">Kampanyalar</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/users') ?>">Kullanıcılar</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/templates') ?>">Şablonlar</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/settings/landing-pages') ?>">Landing sayfaları</a>
    <?php endif; ?>
    <?php if ($show_reports_block): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/reports') ?>">Raporlar</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/events') ?>">Olay günlüğü</a>
    <?php endif; ?>
</div>

<?php if ($show_reports_block && $recent_events !== []): ?>
    <div class="card border-secondary shadow-sm mb-4">
        <div class="card-header border-secondary d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="fw-semibold">Son takip olayları</span>
            <a class="small" href="<?= url('/events') ?>">Tümünü aç →</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-secondary">
                <tr>
                    <th>Zaman</th>
                    <th>Olay</th>
                    <th>Kullanıcı</th>
                    <th>Kampanya</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_events as $ev): ?>
                    <tr>
                        <td class="text-body-secondary small text-nowrap"><?= e($dashFmt($ev['created_at'] !== null ? (string) $ev['created_at'] : null)) ?></td>
                        <td><span class="badge text-bg-dark border border-secondary fw-normal"><?= e((string) ($ev['event_label'] ?? $ev['event_type'] ?? '')) ?></span></td>
                        <td class="small text-break"><?= e((string) ($ev['user_email'] ?? '—')) ?></td>
                        <td class="small text-break"><?= e((string) ($ev['campaign_name'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($show_security_block): ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-secondary shadow-sm h-100">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Son kampanyalar</span>
                    <a class="small" href="<?= url('/campaigns') ?>">Liste →</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-secondary">
                        <tr>
                            <th>Kampanya</th>
                            <th>Durum</th>
                            <th class="text-end">Hedef</th>
                            <th class="text-end">Güncelleme</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($recent_campaigns === []): ?>
                            <tr><td colspan="4" class="text-body-secondary small p-3">Henüz kampanya yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_campaigns as $c): ?>
                                <?php
                                $cid = (int) ($c['id'] ?? 0);
                                $st = (string) ($c['status'] ?? '');
                                $stLabel = $campaign_status_labels[$st] ?? $st;
                                ?>
                                <tr>
                                    <td class="small">
                                        <a href="<?= url('/campaigns/' . $cid) ?>"><?= e((string) ($c['name'] ?? '')) ?></a>
                                    </td>
                                    <td class="small"><span class="badge text-bg-secondary"><?= e($stLabel) ?></span></td>
                                    <td class="text-end small"><?= (int) ($c['targets_count'] ?? 0) ?></td>
                                    <td class="text-end text-body-secondary small text-nowrap"><?= e($dashFmt(isset($c['updated_at']) ? (string) $c['updated_at'] : null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-secondary shadow-sm h-100">
                <div class="card-header border-secondary">
                    <span class="fw-semibold">Son geri bildirimler</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-secondary">
                        <tr>
                            <th>Zaman</th>
                            <th>Kullanıcı</th>
                            <th>Kampanya</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($recent_forms === []): ?>
                            <tr><td colspan="3" class="text-body-secondary small p-3">Kayıt yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_forms as $f): ?>
                                <tr>
                                    <td class="text-body-secondary small text-nowrap"><?= e($dashFmt($f['created_at'] !== null ? (string) $f['created_at'] : null)) ?></td>
                                    <td class="small text-break"><?= e((string) ($f['user_email'] ?? '—')) ?></td>
                                    <td class="small text-break"><?= e((string) ($f['campaign_name'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($recent_credentials !== []): ?>
        <div class="card border-warning border-opacity-50 shadow-sm mb-4">
            <div class="card-header border-warning border-opacity-50 bg-dark">
                <span class="fw-semibold">Son kimlik simülasyonu (yalnızca girilen kullanıcı adı)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-secondary">
                    <tr>
                        <th>Zaman</th>
                        <th>Kullanıcı (hedef)</th>
                        <th>Kampanya</th>
                        <th>Formda yazılan kullanıcı adı</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_credentials as $cc): ?>
                        <tr>
                            <td class="text-body-secondary small text-nowrap"><?= e($dashFmt($cc['created_at'] !== null ? (string) $cc['created_at'] : null)) ?></td>
                            <td class="small text-break"><?= e((string) ($cc['user_email'] ?? '—')) ?></td>
                            <td class="small text-break"><?= e((string) ($cc['campaign_name'] ?? '—')) ?></td>
                            <td class="small"><code><?= e((string) ($cc['username_entered'] ?? '')) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="alert alert-secondary border-secondary mt-2 small mb-0">
    Outlook uyumlu şablonlar için tablo tabanlı HTML kullanın; mobil önizleme ve test gönderimi şablon modülünden yapılır.
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
