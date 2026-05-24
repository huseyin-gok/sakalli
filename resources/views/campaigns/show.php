<?php
/** @var string $title */
/** @var array<string, mixed> $campaign */
/** @var list<array<string, mixed>> $targets */
/** @var int $targets_total */
/** @var array<string, string> $status_labels */
/** @var list<array<string, mixed>> $departments */
/** @var list<array{dn: string, label: string}> $ldap_ous */
/** @var string $ldap_ou_note */
/** @var string $ldap_ou_search_base */
/** @var string $ldap_ou_name_filter */
/** @var list<array<string, mixed>> $users_picker */
/** @var string $csrf */
/** @var bool $can_edit_targets */
/** @var bool $can_start_send_queue */
/** @var string $default_smtp_from_name */
$default_smtp_from_name = $default_smtp_from_name ?? '';
$campaignFromName = trim((string) ($campaign['smtp_from_name'] ?? ''));
$effectiveFromName = $campaignFromName !== '' ? $campaignFromName : $default_smtp_from_name;
/** @var list<array{id: int, name: string}> $landing_pages */
/** @var array<string, string> $interaction_labels */
/** @var bool $feedback_enabled */
/** @var string $feedback_source */
/** @var list<array<string, mixed>> $form_submissions */
/** @var list<array<string, mixed>> $credential_captures */
$landing_pages = $landing_pages ?? [];
$form_submissions = $form_submissions ?? [];
$credential_captures = $credential_captures ?? [];
require dirname(__DIR__) . '/layouts/main.php';

$cid = (int) $campaign['id'];
?>
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a class="small" href="<?= url('/campaigns') ?>">← Kampanyalar</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= url('/campaigns/' . $cid . '/preview') ?>" target="_blank" rel="noopener">
        Önizleme (e-posta + akış)
    </a>
</div>
<h1 class="h4 mb-3"><?= e($title) ?></h1>

<?php if (isset($_GET['queued'])): ?>
    <div class="alert alert-success py-2"><?= (int) $_GET['queued'] ?> hedef e-posta kuyruğuna alındı. Gönderim için cron / Görev Zamanlayıcı ile <code>process_queue.php</code> çalıştırın.</div>
<?php endif; ?>
<?php if (isset($_GET['resent'])): ?>
    <div class="alert alert-success py-2">Tekrar gönderim tamamlandı. Bu istekte <?= (int) $_GET['resent'] ?> e-posta işlendi.</div>
<?php endif; ?>
<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success py-2"><?= (int) $_GET['added'] ?> yeni hedef eklendi (zaten kayıtlı olanlar atlandı).</div>
<?php endif; ?>
<?php if (isset($_GET['cleared'])): ?>
    <div class="alert alert-warning py-2"><?= (int) $_GET['cleared'] ?> hedef kaydı silindi.</div>
<?php endif; ?>
<?php if (isset($_GET['landing_saved'])): ?>
    <div class="alert alert-success py-2">Bilgilendirme şablonu güncellendi.</div>
<?php endif; ?>
<?php if (isset($_GET['interaction_saved'])): ?>
    <div class="alert alert-success py-2">Bu kampanya için tıklama sonrası akış güncellendi.</div>
<?php endif; ?>
<?php if (isset($_GET['from_name_saved'])): ?>
    <div class="alert alert-success py-2">Görünen gönderen adı güncellendi.</div>
<?php endif; ?>
<?php
$err = $_GET['err'] ?? '';
if ($err !== '') {
    $emsg = match ($err) {
        'no_users' => 'Seçime uygun kullanıcı bulunamadı (departman boş veya liste boş).',
        'target_mode' => 'Geçersiz hedef modu.',
        'targets_locked' => 'Bu kampanya durumunda hedef değiştirilemez (yalnızca taslak / planlandı).',
        'confirm' => 'Hedefleri temizlemek için onay kutusunu işaretleyin.',
        'invalid_ou' => 'Geçersiz veya izin verilmeyen OU (yalnızca LDAP_BASE_DN altı seçilebilir).',
        'ldap_down' => 'LDAP bağlantısı kurulamadı; OU altındaki kullanıcılar okunamadı.',
        'send_queue_locked' => 'Bu kampanya durumunda kuyruğa alma yapılamaz (yalnızca taslak / planlandı).',
        'no_pending_targets' => 'Kuyruğa alınacak «pending» hedef yok; hedefler zaten kuyrukta veya işlendi.',
        'resend_now_failed' => 'Tekrar gönderim başlatılamadı; hedef listesi ve SMTP ayarlarını kontrol edin.',
        'no_ldap_match' => (function (): string {
            $pool = isset($_GET['ldap_pool']) ? (int) $_GET['ldap_pool'] : 0;
            $skipped = isset($_GET['skipped']) ? (int) $_GET['skipped'] : 0;
            if ($pool > 0 && $skipped > 0) {
                return 'AD\'de ' . $pool . ' kullanıcı okundu; ' . $skipped . ' kayıt için e-posta üretilemedi (mail/UPN boş ve LDAP_OU_TARGET_EMAIL_DOMAIN tanımlı değil). Ayarlar → LDAP veya .env ile kurumsal alan adını ayarlayın veya AD\'de mail alanını doldurun.';
            }
            if ($pool > 0) {
                return 'Seçilen OU altında AD\'de ' . $pool . ' kullanıcı bulundu; ancak hiçbiri hedef listesine eklenemedi.';
            }

            return 'Seçilen OU altında kullanıcı bulunamadı veya LDAP araması sonuç vermedi.';
        })(),
        default => 'İşlem tamamlanamadı.',
    };
    ?>
    <div class="alert alert-danger py-2"><?= e($emsg) ?></div>
<?php } ?>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-3">Durum</dt>
                    <dd class="col-sm-9"><?= e($status_labels[(string) $campaign['status']] ?? $campaign['status']) ?></dd>
                    <dt class="col-sm-3">Şablon</dt>
                    <dd class="col-sm-9"><?= e((string) $campaign['template_name']) ?></dd>
                    <dt class="col-sm-3">Görünen gönderen</dt>
                    <dd class="col-sm-9">
                        <?php if ($effectiveFromName !== ''): ?>
                            <strong><?= e($effectiveFromName) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">SMTP varsayılanı</span>
                        <?php endif; ?>
                        <?php if ($campaignFromName === '' && $default_smtp_from_name !== ''): ?>
                            <span class="small text-muted">(kampanya özel değil)</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-sm-3">Bilgilendirme sayfası</dt>
                    <dd class="col-sm-9">
                        <?php if (!empty($campaign['landing_page_id']) && !empty($campaign['landing_page_name'])): ?>
                            <?= e((string) $campaign['landing_page_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">Varsayılan (Ayarlar)</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-sm-3">Planlı gönderim</dt>
                    <dd class="col-sm-9"><?= e((string) ($campaign['scheduled_at'] ?? '—')) ?></dd>
                    <dt class="col-sm-3">Batch</dt>
                    <dd class="col-sm-9"><?= (int) $campaign['send_batch_size'] ?></dd>
                    <dt class="col-sm-3">Hedef sayısı</dt>
                    <dd class="col-sm-9"><strong><?= (int) $targets_total ?></strong> kullanıcı</dd>
                    <dt class="col-sm-3">Geri bildirim</dt>
                    <dd class="col-sm-9">
                        <?php if (!empty($feedback_enabled)): ?>
                            <span class="badge text-bg-success">Açık</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Kapalı</span>
                        <?php endif; ?>
                        <span class="small text-muted ms-1">(Kaynak: <?= e($feedback_source ?? '—') ?>)</span>
                    </dd>
                </dl>
                <?php if (!empty($campaign['description'])): ?>
                    <p class="mt-2 mb-0"><?= nl2br(e((string) $campaign['description'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($can_edit_targets): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header">E-posta görünen gönderen adı</div>
    <div class="card-body">
        <p class="small text-muted mb-2">
            Her gönderimde alıcının gördüğü isim (<code>From</code> display name). Boş bırakırsanız
            Ayarlar → SMTP’deki <code>SMTP_FROM_NAME</code> kullanılır. Gönderen <strong>e-posta adresi</strong> değişmez (<code>SMTP_FROM</code>).
        </p>
        <form method="post" action="<?= url('/campaigns/' . $cid . '/smtp-from-name') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="col-md-8">
                <label class="form-label" for="smtp_from_name_edit">Görünen ad</label>
                <input type="text" name="smtp_from_name" id="smtp_from_name_edit" class="form-control" maxlength="191"
                    value="<?= e($campaignFromName) ?>"
                    placeholder="<?= $default_smtp_from_name !== '' ? e($default_smtp_from_name) : 'Örn. İnsan Kaynakları' ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">Takip linki bilgilendirme sayfası</div>
    <div class="card-body">
        <p class="small text-muted mb-2">Kullanıcı e-postadaki takip bağlantısına tıklayınca gösterilecek HTML şablonu. Kampanya durumundan bağımsız güncelleyebilirsiniz.</p>
        <form method="post" action="<?= url('/campaigns/' . $cid . '/landing-page') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="col-md-8">
                <label class="form-label">Şablon</label>
                <select name="landing_page_id" class="form-select">
                    <option value="0" <?= empty($campaign['landing_page_id']) ? 'selected' : '' ?>>Varsayılan (Ayarlar’daki genel metin)</option>
                    <?php foreach ($landing_pages as $lp): ?>
                        <option value="<?= (int) $lp['id'] ?>" <?= (int) ($campaign['landing_page_id'] ?? 0) === (int) $lp['id'] ? 'selected' : '' ?>>
                            <?= e((string) $lp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Kaydet</button>
            </div>
        </form>
        <p class="small mb-0 mt-2"><a href="<?= url('/settings/landing-pages') ?>">Şablonları düzenle</a></p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">Tıklama sonrası kampanya akışı</div>
    <div class="card-body">
        <p class="small text-muted mb-2">
            Bu kampanya için kullanıcı e-postadaki linke tıklayınca hangi akışın açılacağını seçin.
            “Varsayılan” seçeneğinde davranış landing şablonundaki ayarlardan gelir.
        </p>
        <form method="post" action="<?= url('/campaigns/' . $cid . '/interaction-mode') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="col-md-8">
                <label class="form-label">Akış modu</label>
                <select name="interaction_mode" class="form-select">
                    <?php foreach ($interaction_labels as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= (string) ($campaign['interaction_mode'] ?? '') === (string) $k ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Akışı kaydet</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($can_start_send_queue): ?>
<div class="card shadow-sm mb-4 border-secondary">
    <div class="card-header border-bottom border-secondary bg-body-secondary"><strong>E-posta gönderimi</strong></div>
    <div class="card-body">
        <p class="small text-muted mb-2">
            <strong>Pending</strong> hedefler <code>email_queue</code> tablosuna yazılır; sunucuda zamanlayıcı ile
            <code>public/cron/process_queue.php</code> çalıştıkça SMTP üzerinden gönderilir.
            Planlı tarih dolmadan kuyruk bekler (<code>scheduled_at</code>).
        </p>
        <form method="post" action="<?= url('/campaigns/' . $cid . '/send/queue') ?>" class="d-flex flex-wrap align-items-center gap-2">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn-success">Kuyruğa al ve gönderimi başlat</button>
        </form>
        <form method="post" action="<?= url('/campaigns/' . $cid . '/send/now') ?>" class="d-flex flex-wrap align-items-center gap-2 mt-2" onsubmit="return confirm('Tüm hedeflere aynı kampanyayı hemen tekrar göndermek istiyor musunuz?');">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn-outline-warning btn-sm">Tekrar hemen gönder</button>
            <span class="small text-muted">Mevcut hedefler yeniden pending yapılıp aynı anda gönderim denenir.</span>
        </form>
    </div>
</div>
<?php elseif ((string) $campaign['status'] === 'sending'): ?>
<div class="alert alert-info py-2 small mb-4">
    Kampanya <strong>gönderiliyor</strong>.     Kalan mailler için düzenli olarak çalıştırın (proje kökünden):
    <code>php public/cron/process_queue.php</code>
    — batch boyutu: <code>EMAIL_QUEUE_BATCH_SIZE</code> (.env).
</div>
<?php endif; ?>

<?php if ($can_edit_targets): ?>
<div class="card shadow-sm mb-4 border-secondary">
    <div class="card-header border-bottom border-secondary bg-body-secondary"><strong>Kime gönderilecek?</strong></div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Aşağıdan <strong>bir kural</strong> seçip «Hedefleri ekle» deyin. Aynı kullanıcı ikinci kez eklenmez.
            <strong>Tüm aktif kullanıcılar</strong> = <code>users</code> tablosunda <code>is_active = 1</code> olan herkes.
            <strong>Active Directory OU</strong> = seçilen OU ve alt OU’lardaki AD kullanıcıları okunur; yerelde yoksa otomatik <code>users</code> kaydı açılır (kampanya için).             Panele giriş ayrıca <code>LDAP_PANEL_ALLOWED_DN_SUBSTRING</code> ile sınırlıdır.
            Gönderim için yukarıdaki «Kuyruğa al» ile <code>email_queue</code> doldurulur; cron <code>process_queue.php</code> SMTP gönderir.
        </p>
        <form method="post" action="<?= url('/campaigns/' . $cid . '/targets') ?>" class="mb-4">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="target_mode" id="tm_all" value="all_active" checked>
                    <label class="form-check-label" for="tm_all"><strong>Tüm aktif kullanıcılar</strong> — veritabanındaki tüm aktif hesaplar</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="target_mode" id="tm_dept" value="department">
                    <label class="form-check-label" for="tm_dept"><strong>Departmana göre</strong></label>
                </div>
                <div class="ms-4 mb-2">
                    <select name="department_id" class="form-select form-select-sm" style="max-width: 320px;">
                        <option value="0">— Departman seçin —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"><?= e((string) $d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($departments === []): ?>
                        <span class="small text-warning d-block mt-1">Departman yok; önce kullanıcılara departman atayın, AD OU kullanın veya «tüm aktifler» seçin.</span>
                    <?php endif; ?>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="target_mode" id="tm_ldap_ou" value="ldap_ou">
                    <label class="form-check-label" for="tm_ldap_ou"><strong>Active Directory OU</strong> (alt OU’lar dahil)</label>
                </div>
                <div class="ms-4 mb-2">
                    <select name="ou_dn" class="form-select form-select-sm" style="max-width: 100%;">
                        <option value="">— OU seçin (DN) —</option>
                        <?php foreach ($ldap_ous as $ou): ?>
                            <option value="<?= e((string) $ou['dn']) ?>"><?= e((string) $ou['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($ldap_ou_note !== ''): ?>
                        <span class="small text-warning d-block mt-1"><?= e($ldap_ou_note) ?></span>
                    <?php endif; ?>
                    <?php if ($ldap_ou_search_base !== '' && $ldap_ous !== []): ?>
                        <span class="small text-muted d-block mt-1">
                            OU arama tabanı: <code><?= e($ldap_ou_search_base) ?></code>
                            <?php if ($ldap_ou_name_filter !== ''): ?>
                                · yalnızca <code>ou=<?= e($ldap_ou_name_filter) ?></code>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="target_mode" id="tm_sel" value="selected">
                    <label class="form-check-label" for="tm_sel"><strong>Seçili kullanıcılar</strong> (Ctrl/Cmd ile çoklu seçim)</label>
                </div>
                <div class="ms-4">
                    <select name="user_ids[]" id="user_ids_multi" class="form-select" multiple size="12" style="max-width: 100%;">
                        <?php foreach ($users_picker as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e((string) $u['email']) ?> — <?= e((string) ($u['display_name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Listede en fazla 2000 aktif kullanıcı gösterilir.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Hedefleri ekle</button>
        </form>

        <hr>
        <h6 class="text-danger">Hedefleri sıfırla</h6>
        <p class="small text-muted">Bu kampanyadaki tüm <code>campaign_targets</code> kayıtlarını siler. İlgili <code>email_queue</code> satırları hedefe bağlı olduğu için kaskadla silinebilir; takip linkleri geçersizleşir.</p>
        <form method="post" action="<?= url('/campaigns/' . $cid . '/targets/clear') ?>" onsubmit="return confirm('Tüm hedefler silinsin mi?');">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="confirm_clear" value="1">
            <button type="submit" class="btn btn-outline-danger btn-sm">Tüm hedefleri sil</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4 border-warning border-opacity-50">
    <div class="card-header d-flex justify-content-between align-items-center bg-warning bg-opacity-10">
        <span>Kimlik yakalama simülasyonu — girilen kullanıcı adı / parola</span>
        <span class="badge bg-warning text-dark"><?= count($credential_captures) ?> kayıt (son 200)</span>
    </div>
    <div class="card-body">
        <p class="small text-danger mb-2">Hassas veri: yalnızca yetkili kişiler görmeli. Parolalar düz metin saklanır.</p>
        <?php if ($credential_captures === []): ?>
            <p class="text-muted small mb-0">Henüz kayıt yok. Kampanya şablonunda «Kimlik yakalama simülasyonu» açık bir landing seçili olmalı.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Hedef kullanıcı</th>
                        <th>Girilen kullanıcı adı</th>
                        <th>Girilen parola</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($credential_captures as $cc): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e((string) ($cc['created_at'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($cc['user_email'] ?? '')) ?><br><span class="text-muted"><?= e((string) ($cc['user_label'] ?? '')) ?></span></td>
                            <td class="small font-monospace"><?= e((string) ($cc['username_entered'] ?? '')) ?></td>
                            <td class="small font-monospace text-break"><?= e((string) ($cc['password_entered'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Bilgilendirme geri bildirimleri</span>
        <span class="badge bg-secondary"><?= count($form_submissions) ?> kayıt (son 200)</span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2">Takip linkindeki bilgilendirme sayfasından gönderilen formlar. Tüm kampanyalar için özet: <a href="<?= url('/reports') ?>">Raporlar</a>.</p>
        <?php if ($form_submissions === []): ?>
            <p class="text-muted small mb-0">Henüz geri bildirim yok (landing şablonunda geri bildirim açık ve kullanıcı formu göndermiş olmalı).</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Kullanıcı</th>
                        <th>Anladım</th>
                        <th>Mesaj</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($form_submissions as $fs): ?>
                        <?php
                        $raw = (string) ($fs['answers_json'] ?? '{}');
                        $ans = json_decode($raw, true);
                        $comment = is_array($ans) ? trim((string) ($ans['comment'] ?? '')) : '';
                        $und = is_array($ans) ? (string) ($ans['understood'] ?? '') : '';
                        ?>
                        <tr>
                            <td class="small text-nowrap"><?= e((string) ($fs['created_at'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($fs['email'] ?? '')) ?><br><span class="text-muted"><?= e((string) ($fs['user_label'] ?? '')) ?></span></td>
                            <td><?= e(feedback_understood_label($und)) ?></td>
                            <td class="small"><?= $comment !== '' ? nl2br(e($comment)) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Hedef listesi (örnek, en fazla 500 satır)</span>
        <span class="badge text-bg-secondary">Toplam: <?= (int) $targets_total ?></span>
    </div>
    <div class="card-body">
        <?php if ($targets === []): ?>
            <p class="text-muted mb-0">Henüz hedef yok — yukarıdan ekleyin.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>E-posta</th><th>Durum</th></tr></thead>
                    <tbody>
                    <?php foreach ($targets as $ct): ?>
                        <tr>
                            <td><?= e((string) $ct['email']) ?></td>
                            <td><?= e((string) $ct['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
